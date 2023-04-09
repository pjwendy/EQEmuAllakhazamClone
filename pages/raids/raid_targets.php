<?php
$page_title = "Raid Targets By Level of Difficulty";

$print_buffer .= "<p>Here are suggested raid target taken from the main Allakhazam site that are applicable for EQEmu<p>";

$query = "SELECT raid_targets.`level`, raid_targets.killed, npc_types.Name FROM raid_targets INNER JOIN npc_types ON npc_types.id=raid_targets.npc ORDER BY LEVEL ASC ,killed DESC"; 
$result = db_mysql_query($query) or message_die('raid_targets.php', 'MYSQL_QUERY', $query, mysqli_error());
$cpt = 0;
$targets = array();
while ($res = mysqli_fetch_array($result)) {
    $targets[$cpt]["level"] = $res["level"];
    $targets[$cpt]["killed"] = $res["killed"];
    $targets[$cpt]["name"] = $res["Name"];
    $cpt++;
}

$print_buffer .= "<table><tr valign=top><td width=100%>";
$print_buffer .= "<table border=1 style='width:100%' class='display_table container_div datatable'>";
$print_buffer .= "<thead class='menuh'>";
$print_buffer .= "<th class=tab_title>Level</th>";
$print_buffer .= "<th class=tab_title>Name</th>";
$print_buffer .= "<th class=tab_title>Killed</th>";
$print_buffer .= "</thead>";

for ($i = 0; $i <= $cpt; $i++) {

    $print_buffer .= "<tr>
       <td>" . $targets[$i]["level"] . "</td>
       <td class=tab_left>" . $targets[$i]["name"] . "</td>
       <td>" . $targets[$i]["killed"] . "</td>
       </tr>";
}
$print_buffer .= "</table>";
$print_buffer .= "</td><td width=0% nowrap>";
$print_buffer .= "</td></tr></table>";

?>
