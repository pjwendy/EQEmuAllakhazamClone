<?php
/*
 * A zone's map with the guild's camps on it, at the top of the zone page: the client's map lines and labels
 * and the atlas's camps (where the guild hunts: their levels and mobs), from the guild dashboard's
 * /api/zonemap/<zone>. Drawn in the page as SVG: the wheel zooms, dragging pans, a double click resets.
 */
require_once('includes/guild.php');

function guild_zone_map($short_name) {
    $m = guild_dashboard_json('/api/zonemap/' . rawurlencode($short_name));
    if (!$m || (empty($m['lines']) && empty($m['camps']))) {
        return "";
    }
    $json = json_encode(array('lines' => $m['lines'], 'labels' => $m['labels'], 'camps' => $m['camps']));
    $camps = count($m['camps']);
    return "
<div class='zm-wrap'>
  <div class='zm-head'><h2 class='section_header'>Map</h2>
    <span class='zm-note'>" . ($camps ? "$camps camp" . ($camps == 1 ? "" : "s") . " where the guild hunts (circles: bigger, more spawns)" : "no camps here")
    . " · wheel to zoom, drag to pan, double click to reset</span>
    <label class='zm-note'><input type='checkbox' id='zm-labels' checked> place names</label></div>
  <svg id='zm' class='zm' xmlns='http://www.w3.org/2000/svg'></svg>
</div>
<style>
  .zm-wrap { margin: 10px 0 16px; }
  .zm-head { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: baseline; }
  .zm-head h2 { margin: 0; }
  .zm-note { color: var(--muted); font-size: 12px; }
  .zm { width: 100%; height: min(70vh, 640px); background: var(--panel); border: 1px solid var(--line); border-radius: 12px; cursor: grab; display: block; }
  .zm line { stroke: var(--muted); stroke-opacity: .55; vector-effect: non-scaling-stroke; stroke-width: 1; }
  .zm text.place { fill: var(--muted); font: 11px Inter, sans-serif; }
  .zm circle.camp { fill: var(--gold); fill-opacity: .55; stroke: var(--gold-2); vector-effect: non-scaling-stroke; stroke-width: 1.5; }
  .zm circle.camp:hover { fill-opacity: .9; }
  .zm text.lv { fill: var(--text); font: 600 11px Inter, sans-serif; text-anchor: middle; pointer-events: none; }
</style>
<script>
(function () {
  var data = $json, svg = document.getElementById('zm'), NS = 'http://www.w3.org/2000/svg';
  var xs = [], ys = [];
  data.lines.forEach(function (l) { xs.push(l[0], l[2]); ys.push(l[1], l[3]); });
  data.camps.forEach(function (c) { xs.push(c.x); ys.push(c.y); });
  var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs), minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
  var pad = Math.max(maxX - minX, maxY - minY) * 0.03 + 20;
  var home = [minX - pad, minY - pad, maxX - minX + 2 * pad, maxY - minY + 2 * pad], view = home.slice();
  var size = Math.max(home[2], home[3]);
  function el(name, attrs, parent) { var e = document.createElementNS(NS, name); for (var k in attrs) e.setAttribute(k, attrs[k]); (parent || svg).appendChild(e); return e; }
  var lines = el('g', {}), places = el('g', {}), camps = el('g', {});
  data.lines.forEach(function (l) { el('line', {x1: l[0], y1: l[1], x2: l[2], y2: l[3]}, lines); });
  data.labels.forEach(function (p) { var t = el('text', {x: p[0], y: p[1], 'class': 'place'}, places); t.textContent = p[2]; });
  data.camps.forEach(function (c) {
    var r = size * (0.006 + Math.min(c.spawns || 1, 30) * 0.0006);
    var lv = c.levels.length ? (Math.min.apply(null, c.levels) + (Math.max.apply(null, c.levels) > Math.min.apply(null, c.levels) ? '–' + Math.max.apply(null, c.levels) : '')) : '';
    var g = el('g', {}, camps), dot = el('circle', {cx: c.x, cy: c.y, r: r, 'class': 'camp'}, g);
    var tip = el('title', {}, dot);
    tip.textContent = 'Camp' + (lv ? ', levels ' + lv : '') + ' · ' + (c.spawns || 0) + ' spawns' + (c.respawn_s ? ', back in ' + Math.round(c.respawn_s / 60) + ' min' : '')
      + (c.mobs.length ? '\\n' + c.mobs.join(', ') : '') + '\\n/loc ' + Math.round(-c.y) + ', ' + Math.round(-c.x);
    if (lv) { var t = el('text', {x: c.x, y: c.y + r * 0.35, 'class': 'lv', 'font-size': r}, g); t.textContent = lv; }
  });
  function draw() {
    svg.setAttribute('viewBox', view.join(' '));
    var scale = view[2] / home[2];
    places.querySelectorAll('text').forEach(function (t) { t.setAttribute('font-size', size * 0.012 * scale); });
  }
  document.getElementById('zm-labels').onchange = function () { places.style.display = this.checked ? '' : 'none'; };
  function point(e) { var r = svg.getBoundingClientRect(), k = Math.max(view[2] / r.width, view[3] / r.height);
    return [view[0] + (e.clientX - r.left) * k, view[1] + (e.clientY - r.top) * k, k]; }
  svg.addEventListener('wheel', function (e) {
    e.preventDefault();
    var p = point(e), f = e.deltaY > 0 ? 1.2 : 1 / 1.2;
    view = [p[0] - (p[0] - view[0]) * f, p[1] - (p[1] - view[1]) * f, view[2] * f, view[3] * f]; draw();
  }, {passive: false});
  var drag = null;
  svg.addEventListener('mousedown', function (e) { drag = point(e); svg.style.cursor = 'grabbing'; });
  window.addEventListener('mouseup', function () { drag = null; svg.style.cursor = ''; });
  svg.addEventListener('mousemove', function (e) {
    if (!drag) return;
    var r = svg.getBoundingClientRect(), k = drag[2];
    view[0] = drag[0] - (e.clientX - r.left) * k; view[1] = drag[1] - (e.clientY - r.top) * k; draw();
  });
  svg.addEventListener('dblclick', function () { view = home.slice(); draw(); });
  draw();
})();
</script>";
}
