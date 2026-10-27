<?php
/*
 * A quest: where it starts, what to gather, its steps in order (who says or takes what, and what they give),
 * its NPCs and its rewards, from the parser's reading of the scripts.
 */
require_once('includes/quests.php');

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$res = $id ? db_mysql_query("SELECT data FROM guild.quests WHERE id = $id") : null;
$row = $res ? mysqli_fetch_array($res) : null;
if (!$row) {
    $page_title = "Quest";
    $print_buffer .= "<p>No quest $id. <a href='?a=quests'>All quests</a></p>";
    return;
}
$quest = json_decode($row['data'], true);
$page_title = "Quest :: " . $quest['title'];

// item names and zone names
$item_ids = array();
foreach ($quest['gather'] as $g) $item_ids[] = intval($g['item']);
foreach ($quest['steps'] as $st) {
    foreach (array_keys($st['items'] ?? array()) as $i) $item_ids[] = intval($i);
    foreach ($st['gives']['items'] as $c) foreach ($c as $i) $item_ids[] = intval($i);
}
foreach ($quest['rewards']['items'] as $r) foreach ($r[0] as $i) $item_ids[] = intval($i);
$items = array();
if ($item_ids) {
    $res = db_mysql_query("SELECT id, Name FROM $items_table WHERE id IN (" . implode(",", array_unique($item_ids)) . ")");
    while ($res && ($it = mysqli_fetch_array($res))) $items[$it['id']] = $it['Name'];
}
$zone_names = array();
$res = db_mysql_query("SELECT short_name, long_name FROM $zones_table WHERE version = 0");
while ($res && ($z = mysqli_fetch_array($res))) $zone_names[$z['short_name']] = $z['long_name'];

$item = function ($i) use ($items) {
    return "<a href='?a=item&id=" . intval($i) . "'>" . htmlspecialchars($items[$i] ?? "item $i") . "</a>";
};
$zone = function ($z) use ($zone_names) {
    return "<a href='?a=zone&name=" . htmlspecialchars($z) . "'>" . htmlspecialchars($zone_names[$z] ?? $z) . "</a>";
};
$npc = function ($n) use ($zone) {
    $name = htmlspecialchars(str_replace(array("#", "_"), array("", " "), $n['npc']));
    return (!empty($n['npc_id']) ? "<a href='?a=npc&id=" . intval($n['npc_id']) . "'>$name</a>" : $name) . " (" . $zone($n['zone']) . ")";
};

$print_buffer .= "<table class='display_table container_div'><tr><td>";
$print_buffer .= "<h2 class='section_header'>" . htmlspecialchars($quest['title']) . "</h2><ul>";
if ($quest['start']) {
    $print_buffer .= "<li><b>Starts with: </b>" . $npc($quest['start']) . "</li>";
}
$print_buffer .= "<li><b>Level: </b>" . ($quest['level_min'] > 0 ? intval($quest['level_min']) . " and up" : "any") . "</li>";
$print_buffer .= "<li><b>Steps: </b>" . count($quest['steps']) . " across " . count($quest['npcs']) . " NPC" . (count($quest['npcs']) == 1 ? "" : "s") . "</li></ul>";

if ($quest['gather']) {
    $print_buffer .= "<h2 class='section_header'>To gather</h2><ul>";
    foreach ($quest['gather'] as $g) {
        $from = array();
        foreach (($g['drops'] ?? array()) as $d) $from[] = htmlspecialchars($d[0]) . " (" . $zone($d[1]) . ")";
        if (!empty($g['sold'])) $from[] = "sold by a merchant";
        $print_buffer .= "<li>" . $item($g['item']) . ($from ? " <span style='color:var(--muted)'>&mdash; " . implode("; ", $from) . "</span>" : "") . "</li>";
    }
    $print_buffer .= "</ul>";
}

$print_buffer .= "<h2 class='section_header'>Rewards</h2><ul>";
foreach ($quest['rewards']['items'] as $r) {
    $print_buffer .= "<li>" . implode(" or ", array_map($item, $r[0])) . "</li>";
}
if ($quest['rewards']['exp'] > 0) $print_buffer .= "<li>Experience: " . number_format($quest['rewards']['exp']) . "</li>";
if ($quest['rewards']['coin'] > 0) $print_buffer .= "<li>Coin: " . quest_coin($quest['rewards']['coin']) . "</li>";
if (!$quest['rewards']['items'] && $quest['rewards']['exp'] <= 0 && $quest['rewards']['coin'] <= 0) $print_buffer .= "<li>Nothing the scripts give directly</li>";
$print_buffer .= "</ul>";

$print_buffer .= "<h2 class='section_header'>Steps</h2><ol>";
foreach ($quest['steps'] as $st) {
    $text = "<b>" . $npc($st) . "</b> ";
    if ($st['kind'] == 'handin') {
        $parts = array();
        foreach ($st['items'] as $i => $count) $parts[] = ($count > 1 ? intval($count) . " " : "") . $item($i);
        if (!empty($st['coin'])) $parts[] = quest_coin($st['coin']);
        $text .= "takes " . ($parts ? implode(", ", $parts) : "a hand-in");
    } else {
        $kw = array_diff($st['keywords'], array('hail'));
        $text .= $kw ? "when you say <i>" . htmlspecialchars(implode(" / ", $kw)) . "</i>" : "when you hail";
    }
    $conds = array_filter(array_map(function ($c) { return isset($c['not']['say']) ? "" : quest_condition($c); }, $st['conditions']));
    if ($conds) $text .= " <span style='color:var(--muted)'>(if " . implode("; ", $conds) . ")</span>";
    $gives = array();
    foreach ($st['gives']['items'] as $c) $gives[] = implode(" or ", array_map($item, $c));
    if ($st['gives']['exp'] > 0) $gives[] = number_format($st['gives']['exp']) . " experience";
    if ($st['gives']['coin'] > 0) $gives[] = quest_coin($st['gives']['coin']);
    if ($gives) $text .= "<br>&rarr; gives " . implode(", ", $gives);
    $does = $st['does'] ?? array();
    if (!empty($does['flags_set'])) $text .= "<br>&rarr; remembers " . htmlspecialchars(implode(", ", $does['flags_set']));
    if ($st['line'] !== "") $text .= "<br><i style='color:var(--muted)'>&ldquo;" . htmlspecialchars($st['line']) . "&rdquo;</i>";
    $print_buffer .= "<li>$text</li>";
}
$print_buffer .= "</ol>";

$print_buffer .= "<h2 class='section_header'>NPCs</h2><ul>";
foreach ($quest['npcs'] as $n) {
    $at = !empty($n['loc']) ? " <span style='color:var(--muted)'>at " . round($n['loc'][0]) . ", " . round($n['loc'][1]) . "</span>" : "";
    $print_buffer .= "<li>" . $npc($n) . $at . "</li>";
}
$print_buffer .= "</ul></td></tr></table>";
