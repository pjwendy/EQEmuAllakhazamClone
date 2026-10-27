<?php
/*
 * A task: where it's done, its levels and rewards, its description and its steps, from the server's tasks and
 * task_activities as they are now (the zones are each step's, "zones": ids separated by | or ,; the old
 * startzone, minlevel, rewardid, goalid and text1-3 are gone).
 */
$id = (isset($_GET['id']) ? $_GET['id'] : '');
if (!is_numeric($id)) {
    header("Location: ?a=tasks&");
    exit();
}
$id = intval($id);

function task_name_cleanup($name) {
    return str_replace(array("#", "_"), array("", " "), $name);
}

function task_zone_ids($zones) {
    return array_values(array_filter(array_map('intval', preg_split('/[|,]/', (string) $zones)), function ($z) { return $z > 0; }));
}

$task_data = null;
$result = db_mysql_query("SELECT * FROM $task_table WHERE id = $id");
if ($result && mysqli_num_rows($result) > 0) {
    $task_data = mysqli_fetch_array($result);
}
if (!$task_data) {
    $page_title = "Task";
    $print_buffer .= "<p>No task $id.</p>";
    return;
}
$page_title = "Task :: " . $task_data["title"];

$activities = array();
$result = db_mysql_query("SELECT * FROM task_activities WHERE taskid = $id ORDER BY step, activityid");
while ($result && ($row = mysqli_fetch_array($result))) {
    $activities[] = $row;
}

// the zones the steps are in (the old start zone)
$zone_ids = array();
foreach ($activities as $a) {
    foreach (task_zone_ids($a["zones"]) as $z) {
        $zone_ids[$z] = true;
    }
}
$zones = array();
if ($zone_ids) {
    $result = db_mysql_query("SELECT zoneidnumber, short_name, long_name FROM $zones_table WHERE version = 0 AND zoneidnumber IN ("
        . implode(",", array_keys($zone_ids)) . ")");
    while ($result && ($row = mysqli_fetch_array($result))) {
        $zones[$row["zoneidnumber"]] = $row;
    }
}
$zone_link = function ($z) use ($zones) {
    return isset($zones[$z]) ? "<a href='?a=zone&name=" . $zones[$z]["short_name"] . "'>" . htmlspecialchars($zones[$z]["long_name"]) . "</a>" : "zone $z";
};

$print_buffer .= "<table class='display_table container_div'><tr><td>";
$print_buffer .= "<h2 class='section_header'>Where</h2><ul>";
if ($zones) {
    foreach (array_keys($zones) as $z) {
        $print_buffer .= "<li>" . $zone_link($z) . "</li>";
    }
} else {
    $print_buffer .= "<li>Anywhere</li>";
}
$print_buffer .= "</ul>";

$print_buffer .= "<h2 class='section_header'>Information</h2><ul>";
$print_buffer .= "<li><b>Level: </b>" . intval($task_data["min_level"])
    . ($task_data["max_level"] > 0 && $task_data["max_level"] < 255 ? " to " . intval($task_data["max_level"]) : " and up") . "</li>";
$print_buffer .= "<li><b>Repeatable: </b>" . ($task_data["repeatable"] ? "Yes" : "No") . "</li>";
if ($task_data["duration"] > 0) {
    $print_buffer .= "<li><b>Time limit: </b>" . round($task_data["duration"] / 60) . " minutes</li>";
}
$print_buffer .= "<li><b>Rewards: </b><ul>";
if ($task_data["exp_reward"] > 0) {
    $print_buffer .= "<li>Experience: " . number_format($task_data["exp_reward"]) . "</li>";
}
if ($task_data["cash_reward"] > 0) {
    $c = intval($task_data["cash_reward"]);
    $print_buffer .= "<li>Coin: " . floor($c / 1000) . "p " . floor($c % 1000 / 100) . "g " . floor($c % 100 / 10) . "s " . ($c % 10) . "c</li>";
}
foreach (task_zone_ids($task_data["reward_id_list"]) as $item) {
    $print_buffer .= "<li>Item: <a href='?a=item&id=$item'>" . htmlspecialchars(get_field_result("Name", "SELECT Name FROM $items_table WHERE id = $item")) . "</a></li>";
}
if ($task_data["reward_text"] != "") {
    $print_buffer .= "<li>" . htmlspecialchars($task_data["reward_text"]) . "</li>";
}
$print_buffer .= "</ul></li></ul>";

if ($task_data["description"] != "") {
    // (the client's markup in it: colour tags and the like)
    $description = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<c "[^"]*">|<\/c>|\[\d+,?[^\]]*\]/', '', $task_data["description"]))));
    $print_buffer .= "<h2 class='section_header'>Description</h2><p>" . htmlspecialchars($description) . "</p>";
}

$verbs = array(1 => "Deliver", 2 => "Kill", 3 => "Loot", 4 => "Speak with", 5 => "Explore", 6 => "Create", 7 => "Fish for",
    8 => "Forage for", 9 => "Cast on", 10 => "Use a skill on", 11 => "Touch", 13 => "Collect", 100 => "Give coin to");
$print_buffer .= "<h2 class='section_header'>Steps</h2><ol>";
foreach ($activities as $a) {
    $where = array_map($zone_link, task_zone_ids($a["zones"]));
    if ($a["description_override"] != "") {
        $text = htmlspecialchars($a["description_override"]);
    } else {
        $verb   = $verbs[$a["activitytype"]] ?? "Do";
        $count  = $a["goalcount"] > 1 ? intval($a["goalcount"]) . " " : "";
        $what   = $a["item_list"] != "" ? $a["item_list"] : "";
        $target = task_name_cleanup($a["target_name"]);
        if ($a["activitytype"] == 1) {
            $text = "Deliver $count" . htmlspecialchars($what) . ($target != "" ? " to " . htmlspecialchars($target) : "");
        } elseif ($a["activitytype"] == 3) {
            $text = "Loot $count" . htmlspecialchars($what) . ($target != "" ? " from " . htmlspecialchars($target) : "");
        } elseif ($a["activitytype"] == 100) {
            $c = intval($a["goalcount"]);
            $text = "Give " . floor($c / 1000) . "p " . floor($c % 1000 / 100) . "g " . floor($c % 100 / 10) . "s " . ($c % 10) . "c to " . htmlspecialchars($target);
        } else {
            $text = $verb . " " . $count . htmlspecialchars($what != "" ? $what : $target);
        }
    }
    $print_buffer .= "<li>" . $text . ($where ? " <span style='color:var(--muted)'>(" . implode(", ", $where) . ")</span>" : "")
        . ($a["optional"] ? " <i>(optional)</i>" : "") . "</li>";
}
$print_buffer .= "</ol>";
$print_buffer .= "</td></tr></table>";
