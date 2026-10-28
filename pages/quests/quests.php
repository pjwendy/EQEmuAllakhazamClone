<?php
/*
 * The quests, from the quest scripts as the puppeteer's parser read them: filter by zone, a word in the title
 * or the starting NPC, and level.
 */
require_once('includes/quests.php');
$page_title = "Quests";

if (!quests_available()) {
    $print_buffer .= "<p>No quests have been read for this server yet.</p>";
    return;
}

$zone = isset($_GET['zone']) ? addslashes($_GET['zone']) : '';
$q = isset($_GET['q']) ? addslashes(trim($_GET['q'])) : '';
$level = isset($_GET['level']) && is_numeric($_GET['level']) ? intval($_GET['level']) : 0;
$page = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
$per = 100;

$where = array("1=1");
if ($zone != '') $where[] = "q.id IN (SELECT quest_id FROM guild.quest_refs WHERE kind = 'zone' AND ref = '$zone')";
if ($q != '') $where[] = "(q.title LIKE '%$q%' OR q.start_npc LIKE '%$q%')";
if ($level > 0) $where[] = "q.level_min <= $level";
$where[] = "q.zone IN (SELECT short_name FROM $zones_table)";  // (only zones this server has)
$where = implode(" AND ", $where);

$total = intval(get_field_result("n", "SELECT COUNT(*) AS n FROM guild.quests q WHERE $where"));
$result = db_mysql_query("SELECT q.id, q.title, q.zone, q.npcs, q.steps, q.level_min, z.long_name
                          FROM guild.quests q LEFT JOIN $zones_table z ON z.short_name = q.zone AND z.version = 0
                          WHERE $where ORDER BY z.long_name, q.title LIMIT " . (($page - 1) * $per) . ", $per");

$zones = db_mysql_query("SELECT DISTINCT z.short_name, z.long_name FROM guild.quest_refs r JOIN $zones_table z
                         ON z.short_name = r.ref AND z.version = 0 WHERE r.kind = 'zone' ORDER BY z.long_name");
$options = "<option value=''>Any zone</option>";
while ($zones && ($row = mysqli_fetch_array($zones))) {
    $options .= "<option value='" . htmlspecialchars($row['short_name']) . "'" . ($row['short_name'] == stripslashes($zone) ? " selected" : "")
        . ">" . htmlspecialchars($row['long_name']) . "</option>";
}

$print_buffer .= "<table class='display_table container_div'><tr><td>";
$print_buffer .= "<h2 class='section_header'>Quests</h2>";
$print_buffer .= "<form method='get' action='?'><input type='hidden' name='a' value='quests'>
    <select name='zone'>$options</select>
    <input type='text' name='q' placeholder='title or NPC' value='" . htmlspecialchars(stripslashes($q)) . "'>
    <input type='number' name='level' placeholder='my level' min='1' max='125' style='width:6em' value='" . ($level ?: '') . "'>
    <input type='submit' value='Search'></form>";
$print_buffer .= "<p>" . number_format($total) . " quest" . ($total == 1 ? "" : "s")
    . ". Each is worked out from the NPCs' scripts: who starts it, what to bring, what you get.</p>";
$print_buffer .= "<table class='datatable'><tr><th>Quest</th><th>Zone</th><th>NPCs</th><th>Steps</th><th>Level</th></tr>";
while ($result && ($row = mysqli_fetch_array($result))) {
    $print_buffer .= "<tr><td>" . quest_link($row['id'], $row['title']) . "</td><td><a href='?a=zone&name=" . htmlspecialchars($row['zone']) . "'>"
        . htmlspecialchars($row['long_name'] ?: $row['zone']) . "</a></td><td>" . intval($row['npcs']) . "</td><td>" . intval($row['steps'])
        . "</td><td>" . ($row['level_min'] > 0 ? intval($row['level_min']) : "") . "</td></tr>";
}
$print_buffer .= "</table>";

$pages = (int) ceil($total / $per);
if ($pages > 1) {
    $base = "?a=quests&zone=" . urlencode(stripslashes($zone)) . "&q=" . urlencode(stripslashes($q)) . "&level=" . ($level ?: '') . "&page=";
    $print_buffer .= "<p>";
    if ($page > 1) $print_buffer .= "<a href='$base" . ($page - 1) . "'>&laquo; Previous</a> ";
    $print_buffer .= "Page $page of $pages";
    if ($page < $pages) $print_buffer .= " <a href='$base" . ($page + 1) . "'>Next &raquo;</a>";
    $print_buffer .= "</p>";
}
$print_buffer .= "</td></tr></table>";
