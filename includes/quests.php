<?php
/*
 * The quests (the puppeteer's quest parser: guild.quests, quest_npcs and quest_refs, built from the quest
 * scripts): helpers for the quest pages and for the links from NPCs, items and zones.
 */

function quest_link($id, $title) {
    return "<a href='?a=quest&id=" . intval($id) . "'>" . htmlspecialchars($title) . "</a>";
}

// are there quests at all (the tables are the guild's: a server without them just has no Quests)
function quests_available() {
    static $have = null;
    if ($have === null) {
        $r = @db_mysql_query("SELECT 1 FROM guild.quests LIMIT 1");
        $have = $r && mysqli_num_rows($r) > 0;
    }
    return $have;
}

// the quests that touch a zone (short name), an NPC (id) or an item (id): id, title and the roles
function quests_touching($kind, $ref, $limit = 40) {
    if (!quests_available()) return array();
    $kind = ($kind == 'zone' || $kind == 'npc') ? $kind : 'item';
    $ref = addslashes($ref);
    $r = db_mysql_query("SELECT q.id, q.title, GROUP_CONCAT(DISTINCT r.role) AS roles FROM guild.quest_refs r
                         JOIN guild.quests q ON q.id = r.quest_id WHERE r.kind = '$kind' AND r.ref = '$ref'
                         GROUP BY q.id, q.title ORDER BY q.title LIMIT " . intval($limit));
    $out = array();
    while ($r && ($row = mysqli_fetch_array($r))) $out[] = $row;
    return $out;
}

// a list of quests as a section, nothing if none
function quests_section($heading, $kind, $ref) {
    $rows = quests_touching($kind, $ref);
    if (!$rows) return "";
    $words = array('gather' => 'needed', 'takes' => 'handed in', 'gives' => 'given', 'reward' => 'reward');
    $out = "<h2 class='section_header'>$heading</h2><ul>";
    foreach ($rows as $q) {
        $roles = array_filter(explode(",", (string) $q['roles']));
        $roles = array_map(function ($x) use ($words) { return $words[$x] ?? $x; }, $roles);
        $out .= "<li>" . quest_link($q['id'], $q['title'])
            . ($roles ? " <span style='color:var(--muted)'>(" . implode(", ", $roles) . ")</span>" : "") . "</li>";
    }
    return $out . "</ul>";
}

function quest_coin($c) {
    $c = intval($c);
    return floor($c / 1000) . "p " . floor($c % 1000 / 100) . "g " . floor($c % 100 / 10) . "s " . ($c % 10) . "c";
}

// a quest's condition (the parser's structures) in words
function quest_condition($c) {
    if (!is_array($c)) return htmlspecialchars((string) $c);
    $parts = array();
    foreach ($c as $k => $v) {
        if ($k == 'not') {
            $parts[] = "not " . quest_condition($v);
        } elseif ($k == 'say') {
            $parts[] = "says " . htmlspecialchars(implode(" or ", (array) $v));
        } elseif ($k == 'raw') {
            $parts[] = "<code>" . htmlspecialchars($v) . "</code>";
        } else {
            $parts[] = htmlspecialchars($k) . " " . (is_array($v) ? htmlspecialchars(json_encode($v)) : htmlspecialchars((string) $v));
        }
    }
    return implode(", ", $parts);
}
