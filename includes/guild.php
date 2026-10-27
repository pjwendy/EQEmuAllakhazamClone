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
</style>";
