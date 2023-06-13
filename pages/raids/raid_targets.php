<?php

$page_title = "Raid Targets By Level of Difficulty";

$print_buffer .= "<p>Here are suggested raid target taken from the main Allakhazam site that are applicable for EQEmu which can be found <a href=\"https://everquest.allakhazam.com/wiki/eq%3Araid_progression_guide\">here</a><p>";

$levels = array();
$levels[0] = "Stage 0 (~Vox/Nagafen)";
$levels[1] = "Stage 1 (~Kunark)";
$levels[2] = "Stage 2 (~Velious)";
$levels[3] = "Stage 3 (~Avatar of War/NTOV)";
$levels[4] = "Stage 4 (~Emperor Ssraeshza)";
$levels[5] = "Stage 5 (~Vex Thal)";
$levels[6] = "Stage 6 (~Elemental Planes)";
$levels[7] = "Stage 7 (~Plane of Time)";
$levels[8] = "Stage 8 (~Uqua)";
$levels[9] = "Stage 9 (~Qvic)";
$levels[10] = "Stage 10 (~Txevu)";
$levels[11] = "Stage 11 (~Kessdona/Rikkukin)";
$levels[12] = "Stage 12 (~MPG Trials; Tacvi)";
$levels[13] = "Stage 13 (~Anguish; Demiplane Tier 1)";
$levels[14] = "Stage 14 (~Demiplane Tier 2; Theater of Blood)";

$query = "SELECT raid_targets.`level`, raid_targets.killed, raid_targets.killimage, npc_types.Name, npc_types.id FROM raid_targets INNER JOIN npc_types ON npc_types.id=raid_targets.npc ORDER BY LEVEL ASC ,killed DESC"; 
$result = db_mysql_query($query) or message_die('raid_targets.php', 'MYSQL_QUERY', $query, mysqli_error());
$cpt = 0;
$targets = array();
while ($res = mysqli_fetch_array($result)) {
    $targets[$cpt]["level"] = $res["level"];
    $targets[$cpt]["killed"] = $res["killed"];
    $targets[$cpt]["name"] = $res["Name"];
    $targets[$cpt]["id"] = $res["id"];
    $targets[$cpt]["killimage"] = $res["killimage"];
    $cpt++;
}

$print_buffer .= "<table><tr valign=top><td width=100%>";

for ($i = 0; $i < sizeof($levels); $i++) {

    $print_buffer .= "<table border=1 style='width:100%' class='display_table container_div datatable'>";
    $print_buffer .= "<thead class='menuh'>";
    $print_buffer .= "<th class=tab_title>" . $levels[$i] . "</th>";   
    $print_buffer .= "</thead>";

    for ($j = 0; $j < sizeof($targets); $j++) {

        if (strcmp(strval($i),strval($targets[$j]["level"]))==0) {
            
            $killed=" ";
            if (strcmp(strval($targets[$j]["killed"]),"1") == 0) {
                if ($targets[$j]["killimage"] === NULL) 
                    $killed = "*";
                else
                    $killed = "<a href=images/kills/" . $targets[$j]["killimage"] . ">*</a>";
            }
            $name=$targets[$j]["name"];
            $name=str_replace("_"," ",$name);
            $name=str_replace("#","",$name);
            $print_buffer .= "<tr>
                <td class=tab_left>" . $killed . " " . "<a href=?a=npc&id=" . $targets[$j]["id"] . ">" . $name . "</a></td>
                </tr>";
        }
    }

    $print_buffer .= "</table>";

}


$print_buffer .= "</td><td width=0% nowrap>";
$print_buffer .= "</td></tr></table>";
?>
