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
