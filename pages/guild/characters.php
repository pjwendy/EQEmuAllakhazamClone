<?php
/*
 * Characters: everyone playing on the server (not a GM's account), with their level, race, class, guild and
 * where they are, each linked to the character browser (their gear, bags, bank, skills and bots).
 */
require_once('includes/guild.php');

$page_title = "Characters";

$query = "SELECT c.name, c.level, c.race, c.class, c.last_login, z.short_name AS zone, z.long_name AS zone_name,
                 g.name AS guild, g.id AS guild_id, gm.online
          FROM character_data c
          JOIN account a ON a.id = c.account_id
          LEFT JOIN zone z ON z.zoneidnumber = c.zone_id AND z.version = 0
          LEFT JOIN guild_members gm ON gm.char_id = c.id
          LEFT JOIN guilds g ON g.id = gm.guild_id
          WHERE c.deleted_at IS NULL AND a.status < $guild_gm_status
          ORDER BY c.level DESC, c.name";
$result = db_mysql_query($query) or message_die('characters.php', 'MYSQL_QUERY', $query, mysqli_error($database));

$rows = array();
while ($r = mysqli_fetch_array($result)) {
    $rows[] = $r;
}

$print_buffer .= $guild_page_style;
$print_buffer .= "<p class='gp-note'>" . count($rows) . " characters. Each name opens the character browser (gear, bags, bank, skills and bots); a green dot, online, opens their map.</p>";
$print_buffer .= "<table class='display_table container_div datatable gp-table'><thead><tr>"
    . "<th>Name</th><th>Level</th><th>Race</th><th>Class</th><th>Guild</th><th>Zone</th><th>Last on</th></tr></thead><tbody>";
foreach ($rows as $r) {
    $guild = $r['guild'] ? "<a href='?a=guilds#guild-" . intval($r['guild_id']) . "'>" . htmlspecialchars($r['guild']) . "</a>" : "<span class='gp-muted'>—</span>";
    $zone = $r['zone'] ? "<a href='?a=zone&name=" . urlencode($r['zone']) . "'>" . htmlspecialchars($r['zone_name']) . "</a>" : "";
    $last = $r['last_login'] ? date('Y-m-d H:i', $r['last_login']) : "";
    $print_buffer .= "<tr><td>" . guild_online_dot($r['name'], $r['online']) . guild_character_link($r['name'])
        . "</td><td>" . intval($r['level']) . "</td><td>" . ($guild_races[$r['race']] ?? $r['race'])
        . "</td><td>" . ($guild_classes[$r['class']] ?? $r['class']) . "</td><td>$guild</td><td>$zone</td><td class='gp-muted'>$last</td></tr>";
}
$print_buffer .= "</tbody></table>";
