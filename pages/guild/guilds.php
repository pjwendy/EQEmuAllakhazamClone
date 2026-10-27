<?php
/*
 * Guilds: every guild on the server with its roster (not GMs' characters), each member linked to the
 * character browser.
 */
require_once('includes/guild.php');

$page_title = "Guilds";

$query = "SELECT g.id, g.name, g.motd, l.name AS leader FROM guilds g LEFT JOIN character_data l ON l.id = g.leader ORDER BY g.name";
$result = db_mysql_query($query) or message_die('guilds.php', 'MYSQL_QUERY', $query, mysqli_error($database));
$guilds = array();
while ($g = mysqli_fetch_array($result)) {
    $guilds[$g['id']] = $g + array('members' => array());
}

$query = "SELECT gm.guild_id, gm.rank, gm.online, c.name, c.level, c.race, c.class, r.title,
                 z.short_name AS zone, z.long_name AS zone_name
          FROM guild_members gm
          JOIN character_data c ON c.id = gm.char_id AND c.deleted_at IS NULL
          JOIN account a ON a.id = c.account_id AND a.status < $guild_gm_status
          LEFT JOIN guild_ranks r ON r.guild_id = gm.guild_id AND r.rank = gm.rank
          LEFT JOIN zone z ON z.zoneidnumber = c.zone_id AND z.version = 0
          ORDER BY gm.rank, c.level DESC, c.name";
$result = db_mysql_query($query) or message_die('guilds.php', 'MYSQL_QUERY', $query, mysqli_error($database));
while ($m = mysqli_fetch_array($result)) {
    if (isset($guilds[$m['guild_id']])) {
        $guilds[$m['guild_id']]['members'][] = $m;
    }
}

$print_buffer .= $guild_page_style;
$print_buffer .= "<p class='gp-note'>" . count($guilds) . " guild" . (count($guilds) == 1 ? "" : "s")
    . ". Each member's name opens the character browser.</p>";
foreach ($guilds as $g) {
    $members = $g['members'];
    $online = 0;
    $levels = 0;
    foreach ($members as $m) { $online += $m['online'] ? 1 : 0; $levels += $m['level']; }
    $avg = $members ? number_format($levels / count($members), 1) : "–";
    $print_buffer .= "<details class='gp-guild' id='guild-" . intval($g['id']) . "' open><summary><span class='gp-guild-name'>"
        . htmlspecialchars($g['name']) . "</span> <span class='gp-muted'>" . count($members) . " members · $online online · average level $avg"
        . ($g['leader'] ? " · led by " . htmlspecialchars($g['leader']) : "") . "</span></summary>";
    if ($g['motd']) {
        $print_buffer .= "<p class='gp-muted'>" . htmlspecialchars($g['motd']) . "</p>";
    }
    $print_buffer .= "<table class='display_table container_div gp-table'><thead><tr><th>Name</th><th>Rank</th><th>Level</th>"
        . "<th>Race</th><th>Class</th><th>Zone</th></tr></thead><tbody>";
    foreach ($members as $m) {
        $zone = $m['zone'] ? "<a href='?a=zone&name=" . urlencode($m['zone']) . "'>" . htmlspecialchars($m['zone_name']) . "</a>" : "";
        $print_buffer .= "<tr><td>" . guild_online_dot($m['name'], $m['online']) . guild_character_link($m['name'])
            . "</td><td>" . htmlspecialchars($m['title'] ?: "Rank " . $m['rank']) . "</td><td>" . intval($m['level'])
            . "</td><td>" . ($guild_races[$m['race']] ?? $m['race']) . "</td><td>" . ($guild_classes[$m['class']] ?? $m['class'])
            . "</td><td>$zone</td></tr>";
    }
    $print_buffer .= "</tbody></table></details>";
}
