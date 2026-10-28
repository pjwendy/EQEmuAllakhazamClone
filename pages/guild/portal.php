<?php
/*
 * The guild's pages inside Allakhazam: its Home (the guild dashboard: members, levels, wealth, the chronicle, keys and
 * flags), the Daily Reports, Upgrades and Named, and one character's page, upgrades and live map (name=). They're the
 * dashboard's and the runner's own pages, framed so Allakhazam's menu stays.
 */
require_once('includes/guild.php');

$portal = array(
    'home'     => array("The guild", "/home"),
    'reports'  => array("Daily Reports", "/reports/"),
    'upgrades' => array("Upgrades", "/upgrades"),
    'named'    => array("Named", "/named"),
    'releases' => array("Releases", "/releases"),
    'docs'     => array("Docs", "/docs"),
    'character' => array("Character", "/character"),
    'map'      => array("Map", ""),
);
$which = isset($_GET['page']) && isset($portal[$_GET['page']]) ? $_GET['page'] : 'home';
$page_title = $portal[$which][0];
$path = $portal[$which][1];
if ($which == 'docs' && isset($_GET['doc']) && preg_match('/^[a-z0-9-]+$/', $_GET['doc'])) {
    $path .= '/' . $_GET['doc'];  // one document (how items are scored, the headless API)
}
$name = isset($_GET['name']) && preg_match('/^[A-Za-z]{2,64}$/', $_GET['name']) ? $_GET['name']
    : (isset($_GET['c']) && preg_match('/^[A-Za-z]{2,64}$/', $_GET['c']) ? $_GET['c'] : '');
$fixed = false;  // a frame of the viewport's height (a map is a canvas: it has no height of its own to fit)
if ($name !== '' && in_array($which, array('character', 'upgrades'), true)) {
    $path = ($which == 'upgrades' ? "/upgrades/" : "/character/") . rawurlencode($name);
    $page_title = $name . ($which == 'upgrades' ? "'s upgrades" : "");
} elseif ($which == 'map') {
    $page_title = $name !== '' ? "$name's map" : "Map";
    $url = $name !== '' ? guild_map_url($name) : '';
    if ($url === '') {
        $print_buffer .= "<p>" . ($name !== '' ? htmlspecialchars($name) . " isn't online, so there's no live map." : "Which character's map?")
            . " <a href='?a=portal&page=home'>Back to the guild</a></p>";
        return;
    }
    $path = $url;
    $fixed = true;
} elseif ($which == 'character') {
    $which = 'home';
    $path = '/home';
}
$src = htmlspecialchars($which == 'map' ? $path : guild_dashboard_url($path), ENT_QUOTES);

if ($fixed) {
    // a map wants the whole right-hand side: the page's own width limit and padding go while it's showing
    $print_buffer .= "<style>#bg-wrapper { max-width: none !important; } #col-main-inner-3 { padding-left: 0 !important; padding-right: 0 !important; }
        .page-content { margin-right: 0 !important; }</style>";
}
$print_buffer .= "<iframe id='guild-frame' src='$src' title='" . htmlspecialchars($page_title, ENT_QUOTES)
    . "' style='border:0;width:100%;height:" . ($fixed ? "calc(100vh - 135px);min-height:520px" : "900px") . ";display:block' scrolling='no'></iframe>
" . ($fixed ? "" : "<script>
(function () {
    // the page is the same site behind the proxy: its height is readable, so the frame fits it and the page scrolls once
    var f = document.getElementById('guild-frame'), timer = null;
    function fit() {
        if (!document.body.contains(f)) { clearInterval(timer); return; }
        try {
            var d = f.contentDocument;
            if (d && d.documentElement) f.style.height = Math.max(400, d.documentElement.scrollHeight) + 'px';
        } catch (e) { f.style.height = '1600px'; f.scrolling = 'yes'; }
    }
    f.addEventListener('load', function () { fit(); clearInterval(timer); timer = setInterval(fit, 700); });
})();
</script>");
