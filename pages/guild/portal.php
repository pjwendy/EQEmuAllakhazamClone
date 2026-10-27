<?php
/*
 * The guild's pages inside Allakhazam: its Home (the guild dashboard: members, levels, wealth, the chronicle, keys and
 * flags), the Daily Reports, Upgrades and Named. They're the dashboard's own pages, framed so Allakhazam's menu stays.
 */
require_once('includes/guild.php');

$portal = array(
    'home'     => array("The guild", "/home"),
    'reports'  => array("Daily Reports", "/reports/"),
    'upgrades' => array("Upgrades", "/upgrades"),
    'named'    => array("Named", "/named"),
    'releases' => array("Releases", "/releases"),
    'docs'     => array("Docs", "/docs"),
);
$which = isset($_GET['page']) && isset($portal[$_GET['page']]) ? $_GET['page'] : 'home';
$page_title = $portal[$which][0];
$path = $portal[$which][1];
if ($which == 'docs' && isset($_GET['doc']) && preg_match('/^[a-z0-9-]+$/', $_GET['doc'])) {
    $path .= '/' . $_GET['doc'];  // one document (how items are scored, the headless API)
}
$src = htmlspecialchars(guild_dashboard_url($path), ENT_QUOTES);

$print_buffer .= "<iframe id='guild-frame' src='$src' title='" . htmlspecialchars($page_title, ENT_QUOTES)
    . "' style='border:0;width:100%;height:900px;display:block' scrolling='no'></iframe>
<script>
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
</script>";
