<?php
/*
 * The guild's pages (characters, guilds, zone maps): where the character browser and the guild dashboard
 * are, and the names the pages show. The character browser is the guild dashboard's /character/<name>
 * (puppeteer guild/dashboard): through the public proxy (it sets X-Forwarded-Host) it's on the same site,
 * otherwise on the dashboard's own port on this host. GUILD_CHARACTER_URL (a template with {name})
 * overrides both.
 */

function guild_character_url($name) {
    $template = getenv('GUILD_CHARACTER_URL');
    if (!$template) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $template = '/character/{name}';
        } else {
            $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $template = "$scheme://$host:8766/character/{name}";
        }
    }
    return str_replace('{name}', rawurlencode($name), $template);
}

function guild_character_link($name) {
    return "<a href='" . htmlspecialchars(guild_character_url($name), ENT_QUOTES) . "' target='character'>"
        . htmlspecialchars($name, ENT_QUOTES) . "</a>";
}

// the guild dashboard's API from inside the stack (Allakhazam's raid targets and zone maps)
function guild_dashboard_json($path) {
    $base = getenv('GUILD_DASHBOARD_URL') ?: 'http://guild-dashboard:8766';
    $raw = @file_get_contents($base . $path, false, stream_context_create(array('http' => array('timeout' => 15))));
    return $raw ? json_decode($raw, true) : null;
}

// a character's map viewer (the runner's, one port a character), as the guild dashboard's Map button has it:
// through the public proxy /map/<port>/, else that port on the runner's machine (the dashboard's VIEWER_HOST,
// or this host). Only this server's guild has one; others get "".
function guild_map_url($name) {
    static $roster = null;
    if ($roster === null) {
        $g = guild_dashboard_json('/api/guild');
        $roster = array('host' => $g['viewer_host'] ?? '', 'ports' => array(), 'online' => array());
        foreach (($g['roster'] ?? array()) as $c) {
            if (!empty($c['viewer_port'])) $roster['ports'][$c['name']] = intval($c['viewer_port']);
            $roster['online'][$c['name']] = !empty($c['online']);
        }
    }
    if ($name === null) return $roster;
    $port = $roster['ports'][$name] ?? 0;
    if (!$port) return '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return "/map/$port/";
    $host = $roster['host'] ?: preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return "http://$host:$port/";
}

// the online dot: a link to the character's map when it has one
function guild_online_dot($name, $online) {
    $roster = guild_map_url(null);
    if (isset($roster['online'][$name])) {
        $online = $roster['online'][$name];  // (this server's guild: the runner's own record, fresher than the guild table's)
    }
    if (!$online) return '';
    $map = guild_map_url($name);
    $dot = "<span class='gp-on' title='online" . ($map ? ": open " . htmlspecialchars($name, ENT_QUOTES) . "'s map" : "") . "'></span>";
    return $map ? "<a class='gp-maplink' href='" . htmlspecialchars($map, ENT_QUOTES) . "' target='map-" . htmlspecialchars($name, ENT_QUOTES) . "'>$dot</a>" : $dot;
}

$guild_classes = array(1 => "Warrior", 2 => "Cleric", 3 => "Paladin", 4 => "Ranger", 5 => "Shadow Knight", 6 => "Druid",
    7 => "Monk", 8 => "Bard", 9 => "Rogue", 10 => "Shaman", 11 => "Necromancer", 12 => "Wizard", 13 => "Magician",
    14 => "Enchanter", 15 => "Beastlord", 16 => "Berserker");
$guild_races = array(1 => "Human", 2 => "Barbarian", 3 => "Erudite", 4 => "Wood Elf", 5 => "High Elf", 6 => "Dark Elf",
    7 => "Half Elf", 8 => "Dwarf", 9 => "Troll", 10 => "Ogre", 11 => "Halfling", 12 => "Gnome", 128 => "Iksar",
    130 => "Vah Shir", 330 => "Froglok", 522 => "Drakkin");
$guild_gm_status = 80;  // an account at this status or above is a GM's: not listed

$guild_page_style = "<style>
  .gp-note { color: var(--muted); }
  .gp-table { width: 100%; margin-top: 8px; }
  .gp-table td, .gp-table th { padding: 6px 8px; text-align: left; vertical-align: top; }
  .gp-muted { color: var(--muted); font-size: 12px; }
  .gp-guild { margin: 12px 0; background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 10px 14px; }
  .gp-guild summary { cursor: pointer; }
  .gp-guild-name { font: 600 17px Cinzel, Georgia, serif; color: var(--gold-2); }
  .gp-on { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--good); margin-right: 6px; }
  a.gp-maplink { border: 0 !important; padding: 4px 2px; }
  a.gp-maplink .gp-on { box-shadow: 0 0 6px var(--good); }
  a.gp-maplink:hover .gp-on { transform: scale(1.4); }
</style>";

// the guild dashboard's pages (puppeteer guild/dashboard), which the portal pages frame. Behind the guild's proxy
// they're on this same site (/home, /reports/, /upgrades, /named); otherwise on the dashboard's own port.
function guild_dashboard_url($path) {
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        return $path;
    }
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "$scheme://$host:8766$path";
}

// what the guild thinks an item is worth to each class that can use it: guild.item_values (puppeteer
// docs/public/item-scores.md has the arithmetic: stats, weapon, effects and resists, weighted per class and level)
function guild_item_scores($item_id) {
    static $classes = array(1 => 'Warrior', 2 => 'Cleric', 3 => 'Paladin', 4 => 'Ranger', 5 => 'Shadow Knight', 6 => 'Druid',
        7 => 'Monk', 8 => 'Bard', 9 => 'Rogue', 10 => 'Shaman', 11 => 'Necromancer', 12 => 'Wizard', 13 => 'Magician',
        14 => 'Enchanter', 15 => 'Beastlord', 16 => 'Berserker');
    $r = @db_mysql_query("SELECT class_id, stats, weapon, effects, resists, reqlevel, reclevel FROM guild.item_values WHERE item_id = " . intval($item_id));
    $out = array();
    while ($r && ($row = mysqli_fetch_array($r))) {
        $level = max(1, intval($row['reclevel']), intval($row['reqlevel']));
        $rw = ($level < 20 ? 0.1 : ($level < 40 ? 0.3 : ($level < 50 ? 0.6 : 1.0))) * (in_array(intval($row['class_id']), array(1, 3, 5)) ? 1.5 : 1.0);
        $out[] = array('class' => $classes[intval($row['class_id'])] ?? 'Class ' . $row['class_id'], 'level' => $level,
            'score' => round($row['stats'] + $row['weapon'] + $row['effects'] + $rw * $row['resists'], 1),
            'stats' => round($row['stats'], 1), 'weapon' => round($row['weapon'], 1), 'effects' => round($row['effects'], 1),
            'resists' => round($row['resists'], 1));
    }
    usort($out, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return $out;
}

// the item page's section: every class's score with its four parts
function guild_item_score_section($item_id) {
    $rows = guild_item_scores($item_id);
    if (!$rows) return "";
    $html = "<tr><td colspan='2'><h2 class='section_header'>Guild score</h2>"
        . "<p>What the item is worth to each class at its recommended level, in points (one hit point is one point). "
        . "<a href='?a=portal&page=docs&doc=item-scores'>How it's worked out</a>.</p>"
        . "<table class='datatable'><tr><th>Class</th><th>Score</th><th>Stats</th><th>Weapon</th><th>Effects</th><th>Resists</th></tr>";
    foreach ($rows as $r) {
        $html .= "<tr><td>" . htmlspecialchars($r['class']) . "</td><td><b>" . $r['score'] . "</b></td><td>" . $r['stats'] . "</td><td>"
            . $r['weapon'] . "</td><td>" . $r['effects'] . "</td><td>" . $r['resists'] . "</td></tr>";
    }
    return $html . "</table></td></tr>";
}

// the popup's line: the best three classes
function guild_item_score_line($item_id) {
    $rows = array_slice(guild_item_scores($item_id), 0, 3);
    if (!$rows || $rows[0]['score'] <= 0) return "";
    return "<tr><td colspan='2'><b>Guild score: </b>" . implode(", ", array_map(function ($r) {
        return $r['score'] . " " . htmlspecialchars($r['class']);
    }, $rows)) . "</td></tr>";
}

// this server's guild's name (the guild dashboard knows it: GUILD_NAME), kept in a file for ten minutes so a page
// doesn't wait on a request; the first guild in the database if the dashboard can't be asked
function guild_name() {
    static $name = null;
    if ($name !== null) return $name;
    $file = sys_get_temp_dir() . '/guild_name.txt';
    if (is_readable($file) && time() - filemtime($file) < 600) {
        return $name = trim(file_get_contents($file));
    }
    $g = guild_dashboard_json('/api/guild');
    $found = is_array($g) && !empty($g['guild']) ? $g['guild'] : '';
    if ($found === '') {
        $r = @db_mysql_query("SELECT name FROM guilds ORDER BY id LIMIT 1");
        $row = $r ? mysqli_fetch_array($r) : null;
        $found = $row ? $row['name'] : '';
    }
    if ($found !== '') @file_put_contents($file, $found);
    return $name = $found;
}

// the browser tab's title: the guild's name, then the page's
function guild_site_title($page_title = '') {
    $guild = guild_name();
    $base = $guild !== '' ? $guild : "The Guild's Allakhazam";
    return $page_title !== '' ? $base . ' · ' . strip_tags($page_title) : $base . ' · Allakhazam';
}
