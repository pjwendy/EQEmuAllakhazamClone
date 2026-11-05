<?php
/*
 * The guild's pages inside the Codex (the portal: guild_portal_name()): its Home (the guild dashboard: members, levels, wealth, the chronicle, keys and
 * flags), the Daily Reports, Upgrades and Named, and one character's page, upgrades and live map (name=). They're the
 * dashboard's and the runner's own pages, framed so the Codex's menu stays.
 */
require_once('includes/guild.php');

$portal = array(
    'home'     => array("The guild", "/home"),
    'reports'  => array("Daily Reports", "/reports/"),
    'upgrades' => array("Upgrades", "/upgrades"),
    'named'    => array("Named", "/named"),
    'releases' => array("Releases", "/releases"),
    'incidents' => array("Incidents", "/incidents"),
    'backlog' => array("Backlog", "/backlog"),
    'spending' => array("Spending", "/spending"),
    'recordings' => array("Recordings", "/recordings"),
    'atlas'    => array("Atlas", "/atlas"),
    'player'   => array("Recording", "/recordings/play"),
    'docs'     => array("Docs", "/docs"),
    'character' => array("Character", "/character"),
    'map'      => array("Map", ""),
    '3d'       => array("3D", ""),
);
$which = isset($_GET['page']) && isset($portal[$_GET['page']]) ? $_GET['page'] : 'home';
$page_title = $portal[$which][0];
$path = $portal[$which][1];
if ($which == 'docs' && isset($_GET['doc']) && preg_match('/^[a-z0-9-]+$/', $_GET['doc'])) {
    $path .= '/' . $_GET['doc'];  // one document (how items are scored, the headless API)
}
if ($which == 'player' && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $path .= '/' . $_GET['id'];  // one recording, in the player
}
$name = isset($_GET['name']) && preg_match('/^[A-Za-z]{2,64}$/', $_GET['name']) ? $_GET['name']
    : (isset($_GET['c']) && preg_match('/^[A-Za-z]{2,64}$/', $_GET['c']) ? $_GET['c'] : '');
$fixed = false;  // a frame of the viewport's height (a map is a canvas: it has no height of its own to fit)
if ($name !== '' && in_array($which, array('character', 'upgrades'), true)) {
    $path = ($which == 'upgrades' ? "/upgrades/" : "/character/") . rawurlencode($name);
    if ($which == 'character' && isset($_GET['bot']) && preg_match('/^[A-Za-z]{2,64}$/', $_GET['bot'])) {
        $path .= '#' . $_GET['bot'];  // (a bot's chip on the guild home: the owner's profile, at that bot)
    }
    $page_title = $name . ($which == 'upgrades' ? "'s upgrades" : "");
} elseif ($which == 'map' || $which == '3d') {
    $page_title = $name !== '' ? "$name's " . ($which == 'map' ? "map" : "3D view") : $portal[$which][0];
    $url = $name !== '' ? guild_map_url($name) : '';
    if ($url === '') {
        $print_buffer .= guild_player_header($name, $which)
            . "<p>" . ($name !== '' ? htmlspecialchars($name) . " isn't online, so there's no live " . ($which == 'map' ? "map" : "3D view") . "." : "Which character?")
            . " <a href='?a=portal&page=home'>Back to the guild</a></p>";
        return;
    }
    // the 3D view is the dashboard's player, fed by the character's map stream (its events: through the proxy /map/<port>/events)
    $path = $which == 'map' ? $url : guild_dashboard_url('/live?3d&leader=' . rawurlencode($name) . '&events=' . rawurlencode($url . 'events'));
    $fixed = true;
} elseif ($which == 'character') {
    $which = 'home';
    $path = '/home';
}
$src = htmlspecialchars(in_array($which, array('map', '3d'), true) ? $path : guild_dashboard_url($path), ENT_QUOTES);
// one character's map, 3D view, profile or upgrades: the same header over each (the operator, 5 Nov), and the framed page leaves its own
// Map, Profile and Upgrades buttons out (it knows it's in here by the frame's name: codex:<the portal's address>)
$player = $name !== '' && in_array($which, array('map', '3d', 'character', 'upgrades'), true);
if ($player) {
    $print_buffer .= guild_player_header($name, $which);
}

if ($fixed) {
    // a map wants the whole right-hand side: the page's own width limit and padding go while it's showing
    $print_buffer .= "<style>#bg-wrapper { max-width: none !important; } #col-main-inner-3 { padding-left: 0 !important; padding-right: 0 !important; }
        .page-content { margin-right: 0 !important; }</style>";
}
// the frame's name: codex:<the portal's address>, which the pages in it read (and keep as they move on) to send a character's Map, Profile
// and Upgrades here; through the proxy they are on the portal's own address, so none ("codex:": their links are relative)
$codex = !empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? ''
    : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$print_buffer .= "<iframe id='guild-frame' name='" . htmlspecialchars('codex:' . $codex, ENT_QUOTES) . "' src='$src' title='" . htmlspecialchars($page_title, ENT_QUOTES)
    . "' style='border:0;width:100%;height:" . ($fixed ? "calc(100vh - 135px)" : "900px") . ";min-height:520px;display:block' scrolling='no'></iframe>
<script>
(function () {
    // the frame runs down at least as far as the menu on the left (the map's, or the log and chat's, bottom lines up with it);
    // a map fills the window below the banner; the other pages are the height of their own page
    var f = document.getElementById('guild-frame'), timer = null, fixed = " . ($fixed ? "true" : "false") . ";
    function menuBottom() {
        var m = document.querySelector('.side_menu');
        return m && m.offsetParent !== null ? m.getBoundingClientRect().bottom + window.pageYOffset : 0;
    }
    function size(content) {
        var top = f.getBoundingClientRect().top + window.pageYOffset;
        var want = fixed ? window.innerHeight - f.getBoundingClientRect().top - 8 : content;
        f.style.height = Math.max(want, menuBottom() - top, 520) + 'px';
    }
    function fit() {
        if (!document.body.contains(f)) { clearInterval(timer); window.removeEventListener('resize', fit); return; }
        if (fixed) { size(0); return; }
        try {
            var d = f.contentDocument;
            if (d && d.documentElement) size(d.documentElement.scrollHeight);
        } catch (e) { f.style.height = '1600px'; f.scrolling = 'yes'; }
    }
    window.addEventListener('resize', fit);
    f.addEventListener('load', function () { fit(); clearInterval(timer); timer = setInterval(fit, 700); });
    fit();
})();
</script>";
