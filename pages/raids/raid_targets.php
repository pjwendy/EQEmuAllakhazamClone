<?php
/*
 * The guild's raid targets: the same list, and the same view of each, as the guild dashboard's Raid Targets
 * (puppeteer guild/dashboard: raid_targets.json, and what the server says of each target), fetched from the
 * dashboard's /api/targets rather than worked out a second time here. Grouped by expansion; each target's
 * level, zone, whether it's up (or when it's back, or that its event spawns it), whether it would drop an
 * upgrade for anyone in the guild, and the guild's kills of it.
 * The dashboard is GUILD_DASHBOARD_URL (the container's environment), else guild-dashboard:8766 on the
 * stack's network.
 */

$page_title = "Raid Targets";

$dashboard = getenv('GUILD_DASHBOARD_URL') ?: 'http://guild-dashboard:8766';
$context   = stream_context_create(array('http' => array('timeout' => 15)));
$raw       = @file_get_contents($dashboard . '/api/targets', false, $context);
$data      = $raw ? json_decode($raw, true) : null;

function rt_esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function rt_until($t) {
    $s = $t - time();
    if ($s < 3600) return max(1, round($s / 60)) . " min";
    if ($s < 86400) return number_format($s / 3600, 1) . " h";
    return number_format($s / 86400, 1) . " d";
}

function rt_ago($t) {
    $s = time() - $t;
    if ($s < 3600) return max(1, round($s / 60)) . " min ago";
    if ($s < 86400) return round($s / 3600) . " h ago";
    return round($s / 86400) . " d ago";
}

if (!$data || !isset($data['targets'])) {
    $print_buffer .= "<p class='rt-note'>The guild's raid targets aren't available right now (the guild dashboard didn't answer).</p>";
    return;
}

$targets = $data['targets'];
$next    = -1;
foreach ($targets as $i => $t) {
    if (!$t['kills']) { $next = $i; break; }
}

$print_buffer .= "<div id='rt-live'>";
$print_buffer .= "<p class='rt-note'>The mobs the guild is working towards, in order: the same list as the guild dashboard's. "
    . count($targets) . " targets.</p>";

$tiers = array();
foreach ($targets as $i => $t) {
    $tiers[$t['tier'] ?: 'Other'][] = array($i, $t);
}

foreach ($tiers as $tier => $rows) {
    $killed = 0;
    foreach ($rows as $r) { if ($r[1]['kills']) $killed++; }
    $print_buffer .= "<details class='rt-tier'" . ($killed < count($rows) && $tier === $targets[max(0, $next)]['tier'] ? " open" : "") . ">"
        . "<summary><span class='rt-tier-name'>" . rt_esc($tier) . "</span> <span class='rt-count'>" . count($rows)
        . " targets · $killed killed</span></summary>";
    $print_buffer .= "<table class='display_table container_div datatable rt-table' style='width:100%'><thead><tr>"
        . "<th>Target</th><th>Level</th><th>Zone</th><th>Status</th><th>Useful to us</th><th>Our kills</th></tr></thead><tbody>";
    foreach ($rows as $r) {
        list($i, $t) = $r;
        $name = rt_esc($t['name']);
        if (!empty($t['npc_ids'])) {
            $name = "<a href='?a=npc&id=" . intval($t['npc_ids'][0]) . "'>$name</a>";
        }
        if ($i === $next) {
            $name .= " <span class='rt-badge'>Next</span>";
        }
        $note  = $t['note'] ? "<div class='rt-sub'>" . rt_esc($t['note']) . "</div>" : "";
        $lv    = $t['levels'] ? ($t['levels'][0] == $t['levels'][1] ? $t['levels'][0] : $t['levels'][0] . "–" . $t['levels'][1]) : "?";
        if (!$t['known']) {
            $status = "<span class='rt-down'>not on this server</span>";
        } elseif (!empty($t['scripted'])) {
            $status = "<span class='rt-down' title='No spawn point: its encounter spawns it'>spawned by its event</span>";
        } elseif ($t['up']) {
            $status = "<span class='rt-up'>up</span>" . ($t['count'] > 1 ? " <span class='rt-down'>(" . $t['up'] . " of " . $t['count'] . ")</span>" : "");
        } elseif ($t['next_up']) {
            $status = "<span class='rt-down'>back in " . rt_until($t['next_up']) . "</span>";
        } else {
            $status = "<span class='rt-down'>down</span>";
        }
        $v = $t['value'];
        if (!$v) {
            $useful = "<span class='rt-down'>working it out…</span>";
        } elseif ($v['worth_farming']) {
            $useful = $v['drops_now'] . " drop" . ($v['drops_now'] == 1 ? "" : "s") . " · " . $v['people_now'] . " of us"
                . "<div class='rt-sub'>best: " . rt_esc($v['best']) . "</div>";
        } else {
            $useful = "<span class='rt-down'>outgrown: no upgrades left for anyone</span>";
        }
        $kills = $t['kills'] ? $t['kills'] . " · last by " . rt_esc($t['last_by']) . " " . rt_ago($t['last_kill'])
                             : "<span class='rt-down'>not yet</span>";
        $print_buffer .= "<tr" . ($i === $next ? " class='rt-next'" : "") . "><td><b>$name</b>$note</td><td>$lv</td><td>"
            . rt_esc(implode(", ", $t['zones'])) . "</td><td>$status</td><td>$useful</td><td>$kills</td></tr>";
    }
    $print_buffer .= "</tbody></table></details>";
}

$print_buffer .= "</div><script>
(function () {
    // kills and respawns change: read the list again every minute while the page is open, keeping the tiers that are open
    var live = document.getElementById('rt-live');
    var timer = setInterval(function () {
        if (!document.body.contains(live)) { clearInterval(timer); return; }
        if (document.hidden) return;
        fetch('?a=raid_targets&v_ajax', {cache: 'no-store'}).then(function (r) { return r.text(); }).then(function (html) {
            var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('rt-live');
            if (!fresh) return;
            var open = {};
            live.querySelectorAll('details.rt-tier').forEach(function (d) { open[d.querySelector('.rt-tier-name').textContent] = d.open; });
            fresh.querySelectorAll('details.rt-tier').forEach(function (d) {
                var k = d.querySelector('.rt-tier-name').textContent;
                if (k in open) d.open = open[k];
            });
            live.innerHTML = fresh.innerHTML;
        }).catch(function () {});
    }, 60000);
})();
</script>";
$print_buffer .= "<style>
  .rt-note { color: var(--muted); }
  .rt-tier { margin: 12px 0; background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 10px 14px; }
  .rt-tier summary { cursor: pointer; }
  .rt-tier-name { font: 600 17px Cinzel, Georgia, serif; color: var(--gold-2); }
  .rt-count, .rt-sub, .rt-down { color: var(--muted); font-size: 12px; }
  .rt-up { color: var(--good); font-weight: 600; }
  .rt-badge { font: 600 11px Inter, sans-serif; color: var(--bg); background: var(--gold); border-radius: 999px; padding: 1px 8px; }
  .rt-table { margin-top: 10px; }
  .rt-table td { vertical-align: top; padding: 6px 8px; }
  tr.rt-next td { background: var(--panel-2) !important; }
</style>";
