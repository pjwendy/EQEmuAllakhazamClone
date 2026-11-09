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

// the guild dashboard's API from inside the stack (the Codex's raid targets and zone maps)
function guild_dashboard_json($path) {
    $base = getenv('GUILD_DASHBOARD_URL') ?: 'http://guild-dashboard:8766';
    $raw = @file_get_contents($base . $path, false, stream_context_create(array('http' => array('timeout' => 15))));
    return $raw ? json_decode($raw, true) : null;
}

// the signed-in operator? (the public pages' Google sign-in: guild-auth, oauth2-proxy, asked with this visitor's own cookie; 202 signed in).
// For what only the operator sees, such as the Visitors menu entry. Off the public site (no cookie, no sign-in) it is false.
function guild_is_operator() {
    static $yes = null;
    if ($yes !== null) return $yes;
    $cookie = $_SERVER['HTTP_COOKIE'] ?? '';
    if (strpos($cookie, '_guild_auth') === false) return $yes = false;
    $base = getenv('GUILD_AUTH_URL') ?: 'http://guild-auth:4180';
    $ctx = stream_context_create(array('http' => array('timeout' => 2, 'ignore_errors' => true,
        'header' => "Cookie: $cookie\r\nX-Forwarded-Proto: https\r\nX-Forwarded-Host: " . ($_SERVER['HTTP_HOST'] ?? '') . "\r\n")));
    @file_get_contents($base . '/oauth2/auth', false, $ctx);
    $status = isset($http_response_header[0]) ? intval(explode(' ', $http_response_header[0])[1] ?? 0) : 0;
    return $yes = ($status === 202);
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
// the header over one character's pages in the portal (the operator, 5 Nov): 2D (the map), 3D, Profile and Upgrades always in the same place,
// then the character's name and what is showing. 2D and 3D are off while it isn't online (both are its live map's)
function guild_player_header($name, $which) {
    $q = rawurlencode($name);
    $online = $name !== '' && guild_map_url($name) !== '';
    $what = array('map' => '2D map', '3d' => '3D view', 'history' => 'History', 'corpses' => 'Corpses', 'character' => 'Profile', 'upgrades' => 'Upgrades');
    $btn = function ($page, $label, $cls, $on, $title) use ($q, $which) {
        $cur = $which == $page ? ' cur' : '';
        return $on ? "<a class='ph-btn $cls$cur' href='?a=portal&amp;page=$page&amp;name=$q' title='$title'>$label</a>"
            : "<span class='ph-btn $cls off' title='$title (not online)'>$label</span>";
    };
    return "<style>
  .ph { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 10px; padding: 8px 4px 10px; }
  .ph .ph-btn, .ph a.ph-btn:link, .ph a.ph-btn:visited, .ph a.ph-btn:hover { color: #15110d !important; }
  .ph-btn { font: 600 13px Inter, system-ui, sans-serif; border-radius: 8px; padding: 4px 14px; text-decoration: none !important;
    border: 2px solid transparent; }
  .ph-btn:hover { filter: brightness(1.12); } .ph-btn.cur { border-color: currentColor; box-shadow: 0 0 0 2px rgba(0,0,0,.25) inset; }
  .ph-map { background: #d8a94a; } .ph-3d { background: #b48ce0; } .ph-profile { background: #6fa8dc; } .ph-upgrades { background: #6fbf73; }
  .ph-history { background: #e08a3c; } .ph-corpses { background: #c9c2b4; }
  .ph-btn.off { opacity: .4; cursor: default; filter: none; }
  .ph-name { font: 700 18px Cinzel, Georgia, serif; margin-left: 6px; } .ph-what { opacity: .7; font-size: 15px; }
</style><div class='ph'>"
        . $btn('map', '2D', 'ph-map', $online, htmlspecialchars($name, ENT_QUOTES) . "'s live map")
        . $btn('3d', '3D', 'ph-3d', $online, htmlspecialchars($name, ENT_QUOTES) . " in 3D, as the game draws it")
        . $btn('history', 'History', 'ph-history', true, "Where it has been and why: its trail, the last 24 hours, to replay")
        . $btn('character', 'Profile', 'ph-profile', true, "Its gear, bags, bank, corpses, upgrades and bots")
        . "<span class='ph-name'>" . htmlspecialchars($name) . "</span><span class='ph-what'>&middot; " . $what[$which] . "</span></div>";
}

function guild_dashboard_url($path) {
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        return $path;
    }
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return "$scheme://$host:8766$path";
}

// class names by id (characters' and bots' class numbers)
function guild_class_name($id) {
    static $classes = array(1 => 'Warrior', 2 => 'Cleric', 3 => 'Paladin', 4 => 'Ranger', 5 => 'Shadow Knight', 6 => 'Druid',
        7 => 'Monk', 8 => 'Bard', 9 => 'Rogue', 10 => 'Shaman', 11 => 'Necromancer', 12 => 'Wizard', 13 => 'Magician',
        14 => 'Enchanter', 15 => 'Beastlord', 16 => 'Berserker');
    return $classes[intval($id)] ?? '';
}

// the global search's characters (not GMs') and bots: SQL for the count and the list. A character links to its page
// in the character browser, a bot to its card on its owner's page.
function guild_search_sql($what, $name) {
    global $guild_gm_status;
    if ($what == 'characters') {
        return "FROM character_data c JOIN account a ON a.id = c.account_id WHERE c.deleted_at IS NULL AND a.status < $guild_gm_status AND c.name LIKE '%$name%'";
    }
    return "FROM bot_data b JOIN character_data o ON o.id = b.owner_id AND o.deleted_at IS NULL JOIN account a ON a.id = o.account_id WHERE a.status < $guild_gm_status AND b.name LIKE '%$name%'";
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
// The portal's name: the Codex of this server's guild (Codex Intelligentia Artificialis; Bona's and Mala's on eq2 and eq3)
function guild_portal_name() {
    $guild = guild_name();
    return 'Codex ' . ($guild !== '' ? $guild : 'Intelligentia Artificialis');
}

function guild_site_title($page_title = '') {
    $base = guild_portal_name();
    return $page_title !== '' ? $base . ' · ' . strip_tags($page_title) : $base;
}

// What this server has: the zones past its expansion, or only a GM may enter, don't exist as far as the guild goes
// (puppeteer soldiers/available.py: the same two views in the guild's database, made there, by the dashboard, or here).
// Every query through $zones_table then sees only the zones players can reach; era lists and quests are filtered by it.
function guild_zone_views() {
    return array(
        "CREATE OR REPLACE VIEW guild.current_expansion AS
         SELECT CAST(COALESCE((SELECT rule_value FROM peq.rule_values WHERE ruleset_id = 1 AND rule_name = 'Expansion:CurrentExpansion'),
                              (SELECT rule_value FROM peq.rule_values WHERE ruleset_id = 0 AND rule_name = 'Expansion:CurrentExpansion'),
                              '99') AS SIGNED) AS value",
        "CREATE OR REPLACE VIEW guild.zone_active AS
         SELECT z.* FROM peq.zone z, guild.current_expansion c
         WHERE z.min_status = 0 AND (z.expansion <= c.value)
           AND (z.min_expansion < 0 OR z.min_expansion <= c.value) AND (z.max_expansion < 0 OR z.max_expansion >= c.value)",
    );
}

function guild_use_active_zones() {
    global $zones_table, $database;
    $probe = function () use ($database) {
        try { return (bool) mysqli_query($database, "SELECT 1 FROM guild.zone_active LIMIT 1"); } catch (\Throwable $e) { return false; }
    };
    if (!$probe()) {
        foreach (guild_zone_views() as $sql) {
            try { mysqli_query($database, $sql); } catch (\Throwable $e) { return; }
        }
    }
    if ($probe()) {
        $zones_table = "guild.zone_active";
    }
}

// the short names of the zones open to players
function guild_active_zone_names() {
    static $names = null;
    if ($names === null) {
        global $zones_table, $database;
        $names = array();
        try {
            $r = mysqli_query($database, "SELECT DISTINCT short_name FROM $zones_table");
            while ($r && ($row = mysqli_fetch_row($r))) $names[$row[0]] = true;
        } catch (\Throwable $e) {
            $names = array();
        }
    }
    return $names;
}

// an era's page: only the zones this server has (the lists are fixed text)
function guild_filter_zone_lines($html) {
    $active = guild_active_zone_names();
    if (!$active) return $html;
    return implode("\n", array_filter(explode("\n", $html), function ($line) use ($active) {
        return !preg_match('/\?a=zone&name=([A-Za-z0-9_]+)/', $line, $m) || isset($active[$m[1]]);
    }));
}

// the eras with nothing left in them are dropped from the list of eras
function guild_filter_eras($html) {
    $active = guild_active_zone_names();
    if (!$active) return $html;
    return preg_replace_callback('/<li><a href=\?a=zone_era&era=([a-z]+)[^>]*>.*?<\/a><\/li>\s*/s', function ($m) use ($active) {
        $file = 'pages/zones/zones_by_era/' . $m[1] . '.php';
        $text = is_readable($file) ? file_get_contents($file) : '';
        preg_match_all('/\?a=zone&name=([A-Za-z0-9_]+)/', $text, $names);
        foreach ($names[1] as $n) if (isset($active[$n])) return $m[0];
        return '';
    }, $html);
}

// Items follow their zones: one that only the closed zones' NPCs give, and nothing in the open ones, is on
// guild.item_hidden (puppeteer soldiers/available.py works the list out) and isn't here: $items_table is a view without them.
function guild_use_active_items() {
    global $items_table, $database;
    $probe = function () use ($database) {
        try { return (bool) mysqli_query($database, "SELECT 1 FROM guild.item_active LIMIT 1"); } catch (\Throwable $e) { return false; }
    };
    if (!$probe()) {
        try {
            mysqli_query($database, "CREATE TABLE IF NOT EXISTS guild.item_hidden (item_id INT UNSIGNED PRIMARY KEY, built DOUBLE)");
            mysqli_query($database, "CREATE OR REPLACE VIEW guild.item_active AS SELECT i.* FROM peq.items i LEFT JOIN guild.item_hidden h ON h.item_id = i.id WHERE h.item_id IS NULL");
        } catch (\Throwable $e) {
            return;
        }
    }
    if ($probe()) {
        $items_table = "guild.item_active";
    }
}

// a character's Profile in the portal (under its header); a bot: its owner's, at the bot
function guild_profile_link($name, $bot = '') {
    $href = "?a=portal&page=character&name=" . rawurlencode($name) . ($bot !== '' ? "&bot=" . rawurlencode($bot) : "");
    return "<a href='" . htmlspecialchars($href, ENT_QUOTES) . "'>" . htmlspecialchars($bot !== '' ? $bot : $name) . "</a>";
}

// the item page's section: who has the item, the players' characters and the bots (the operator, 7 Nov: "We should list on the item page
// who else has it (both players and bots)"), from the guild dashboard (/api/holders/<id>: worn, carried or banked, an augment too)
function guild_item_holders_section($item_id) {
    $d = guild_dashboard_json('/api/holders/' . intval($item_id));
    if (!$d) return "";
    $players = $d['players'] ?? array();
    $bots = $d['bots'] ?? array();
    $line = function ($h) {
        $who = isset($h['owner']) ? guild_profile_link($h['owner'], $h['name']) . " <span class='gp-muted'>(" . htmlspecialchars($h['owner']) . "'s bot)</span>"
            : guild_profile_link($h['name']);
        return "<li>$who &middot; " . intval($h['level']) . " " . htmlspecialchars(guild_class_name($h['class']))
            . ($h['count'] > 1 ? " &middot; &times;" . intval($h['count']) : "") . " <span class='gp-muted'>" . htmlspecialchars(implode(", ", $h['where'])) . "</span></li>";
    };
    $html = "<tr><td colspan='2'><h2 class='section_header'>Who has it</h2>";
    if (!$players && !$bots) {
        return $html . "<p>No player or bot has one.</p></td></tr>";
    }
    if ($players) {
        $html .= "<b>Players</b> (" . count($players) . ")<ul>" . implode("", array_map($line, $players)) . "</ul>";
    }
    if ($bots) {
        $html .= "<b>Bots</b> (" . count($bots) . ")<ul>" . implode("", array_map($line, $bots)) . "</ul>";
    }
    return $html . "</td></tr>";
}

// the NPC page's section: the dash cam's recordings of it (the operator, 7 Nov: "Add a recordings option to the mob page so we can see it
// being killed"): its kills, the upgrades it dropped and the deaths it caused, each to watch in the portal's player
function guild_npc_recordings_section($npc_id) {
    $d = guild_dashboard_json('/api/recordings?npc=' . intval($npc_id));
    $recs = $d['recordings'] ?? array();
    $html = "<h2 class='section_header'>Recordings</h2>";
    if (!$recs) {
        return $html . "<p>No recording of it yet (a named kill, an upgrade it drops or a death it causes keeps one).</p>";
    }
    $kinds = array('raid' => 'Raid fight', 'named' => 'Killed', 'upgrade' => 'Upgrade drop', 'death' => 'Killed one of ours', 'close call' => 'Close call', 'manual' => 'Recorded');
    $html .= "<table class='datatable'><tr><th>When</th><th>What</th><th>Group</th><th></th></tr>";
    $tz = new DateTimeZone(getenv('GUILD_TZ') ?: 'Europe/Stockholm');  // (the container's clock is UTC; the guild's is Sweden's)
    foreach (array_slice($recs, 0, 50) as $r) {
        $when = (new DateTime('@' . intval($r['started'])))->setTimezone($tz)->format('D j M H:i');
        $html .= "<tr><td style='white-space:nowrap'>" . $when . "</td><td><b>"
            . htmlspecialchars($kinds[$r['kind']] ?? $r['kind']) . "</b> &middot; " . htmlspecialchars($r['title']) . "</td><td>" . guild_profile_link($r['leader'])
            . "</td><td style='white-space:nowrap'><span class='gp-pin' data-rid='" . intval($r['id']) . "' data-keep='" . (empty($r['keep']) ? 0 : 1)
            . "'></span> <a href='?a=portal&amp;page=player&amp;id=" . intval($r['id']) . "'>Watch</a></td></tr>";
    }
    // the keep pins (the operator, 9 Nov: "add the pin everywhere a recording is listed"): the dashboard's links.js draws and works them, on
    // the public site (the same address as the dashboard; the signed-in operator); where /links.js isn't there (the Codex's own port) none show
    $pins = "<script src='/links.js'></script><script>(function () { if (typeof GL === 'undefined') return;"
          . " document.querySelectorAll('span.gp-pin').forEach(function (s) { s.innerHTML = GL.pin({id: +s.dataset.rid, keep: s.dataset.keep === '1'}); });"
          . " GL.wirePins(document); })();</script>";
    return $html . "</table>" . (count($recs) > 50 ? "<p class='gp-muted'>The newest 50 of " . count($recs) . ".</p>" : "") . $pins;
}

// a key's doors: where it opens a locked door (peq.doors keyitem), in the zones players can reach; for the item's page (the operator, 8 Nov:
// "we just need to be aware of how to get through them and unlock them")
function guild_item_doors_section($item_id) {
    global $zones_table;
    $id = intval($item_id);
    $r = @db_mysql_query("SELECT d.zone, d.doorid, d.pos_x, d.pos_y, d.pos_z, d.lockpick, d.nokeyring, z.long_name FROM doors d
                          JOIN $zones_table z ON z.short_name = d.zone AND z.version = 0 WHERE d.version = 0 AND d.keyitem = $id ORDER BY z.long_name, d.doorid");
    $rows = array();
    while ($r && ($row = mysqli_fetch_array($r))) $rows[] = $row;
    if (!$rows) return "";
    $out = "<tr><td colspan='2'><h2 class='section_header'>Opens</h2><ul>";
    foreach ($rows as $d) {
        $out .= "<li>a locked door in <a href='?a=zone&name=" . urlencode($d['zone']) . "'>" . htmlspecialchars($d['long_name']) . "</a> (door "
            . intval($d['doorid']) . ", /loc " . round($d['pos_y']) . ", " . round($d['pos_x']) . ", " . round($d['pos_z']) . ")"
            . (intval($d['nokeyring']) ? " <span style='color:var(--muted)'>(not kept on the keyring: carry the key)</span>"
                                       : " <span style='color:var(--muted)'>(onto the keyring the first time it's used)</span>")
            . (intval($d['lockpick']) ? " <span style='color:var(--muted)'>· or picked at Pick Lock " . intval($d['lockpick']) . "</span>" : "") . "</li>";
    }
    return $out . "</ul></td></tr>";
}
