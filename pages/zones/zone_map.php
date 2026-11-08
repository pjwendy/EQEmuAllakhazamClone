<?php
/*
 * A zone's map at the top of the zone page: the client's map lines and labels and the atlas's camps (from the guild dashboard's
 * /api/zonemap/<zone>, in the page), and over them, fetched from the dashboard's /api/zonelayers/<zone> once the page is up, every
 * layer the operator asked for to examine the navigation by hand (8 Nov: "see the navmesh in the maps ... show zonelines so I can see
 * how the navmesh is connecting them ... spawn points ... ladders, books, boats etc. Each type of data needs to be on a toggle"):
 * the navmesh (a canvas under the lines: each island its own colour, the biggest green), its off-mesh links, the zone lines (green when
 * the survey's walk reached them, red when it didn't), doors (books and teleports, lifts, locked doors, the rest), the navmesh's ladders,
 * jumps, swims and teleports, boats, spawn points, the survey's failed walks and the leaders' stuck spots of the last week. A height
 * slice shows one floor of a zone at a time. The wheel zooms, dragging pans, a double click resets; the toggles are remembered.
 */
require_once('includes/guild.php');

function guild_zone_map($short_name) {
    $m = guild_dashboard_json('/api/zonemap/' . rawurlencode($short_name));
    if (!$m || (empty($m['lines']) && empty($m['camps']))) {
        return "";
    }
    $json = json_encode(array('lines' => $m['lines'], 'labels' => $m['labels'], 'camps' => $m['camps']));
    $layers_url = json_encode(guild_dashboard_url('/api/zonelayers/' . rawurlencode($short_name)));
    $camps = count($m['camps']);
    return "
<div class='zm-wrap'>
  <div class='zm-head'><h2 class='section_header'>Map</h2>
    <span class='zm-note'>" . ($camps ? "$camps camp" . ($camps == 1 ? "" : "s") . " where the guild hunts" : "no camps here")
    . " · wheel to zoom, drag to pan, double click to reset · hover anything for details</span>
    <span class='zm-note' id='zm-status'>loading the navigation layers…</span></div>
  <div class='zm-toggles' id='zm-toggles'></div>
  <div class='zm-legend' id='zm-navlegend' hidden></div>
  <div class='zm-legend' id='zm-stucklegend' hidden></div>
  <div class='zm-legend' id='zm-auditlegend' hidden></div>
  <div class='zm-height'><label><input type='checkbox' id='zm-slice'> Height slice</label>
    <input type='range' id='zm-z' min='-1000' max='1000' step='1' disabled> <span id='zm-zval' class='zm-note'></span>
    <label class='zm-note'>± <input type='number' id='zm-zw' value='40' min='5' max='500' step='5' style='width:56px'></label>
    <button type='button' id='zm-full' class='zm-btn'>⤢ Full screen</button></div>
  <div class='zm-stage' id='zm-stage'>
    <canvas id='zm-nav' class='zm-canvas'></canvas>
    <svg id='zm' class='zm' xmlns='http://www.w3.org/2000/svg'></svg>
    <div class='zm-hover' id='zm-hover' hidden></div>
  </div>
</div>
<style>
  .zm-wrap { margin: 10px 0 16px; }
  .zm-head { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: baseline; }
  .zm-head h2 { margin: 0; }
  .zm-note { color: var(--muted); font-size: 12px; }
  .zm-toggles { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 6px; }
  .zm-toggles label { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; border: 1px solid var(--line); border-radius: 999px;
    padding: 2px 9px 2px 6px; cursor: pointer; user-select: none; background: var(--panel); }
  .zm-toggles label.off { opacity: .5; }
  .zm-toggles label i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
  .zm-toggles label small { color: var(--muted); }
  .zm-toggles input { margin: 0; }
  .zm-height { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 12px; margin-bottom: 6px; }
  .zm-height input[type=range] { width: min(320px, 50vw); }
  .zm-stage { position: relative; width: 100%; height: min(75vh, 720px); background: var(--panel); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  .zm-btn { font: 12px Inter, sans-serif; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); color: var(--text); padding: 3px 10px; cursor: pointer; }
  .zm-wrap.full { position: fixed; inset: 0; z-index: 2147483000; margin: 0; padding: 10px 14px; background: var(--panel, #fffdf6); overflow: auto; display: flex; flex-direction: column; }
  .zm-wrap.full .zm-stage { flex: 1; height: auto; min-height: 300px; }
  .zm-legend { font-size: 12px; line-height: 1.5; border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px; margin: 0 0 8px; background: var(--panel); }
  .zm-legend b { font-weight: 600; }
  .zm-legend .sw { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -2px; margin: 0 4px 0 0; }
  .zm-legend .isl { display: inline-flex; align-items: center; margin: 2px 10px 0 0; color: var(--muted); }
  .zm-hover { position: absolute; pointer-events: none; font-size: 12px; background: var(--panel); border: 1px solid var(--line); border-radius: 8px;
    padding: 5px 8px; box-shadow: 0 4px 14px rgba(0,0,0,.18); max-width: 300px; z-index: 2; }
  .zm-canvas, .zm { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
  .zm { cursor: grab; }
  .zm line.ml { stroke: var(--muted); stroke-opacity: .55; vector-effect: non-scaling-stroke; stroke-width: 1; }
  .zm text { font-family: Inter, sans-serif; }
  .zm text.place { fill: var(--muted); }
  .zm circle.camp { fill: var(--gold); fill-opacity: .45; stroke: var(--gold-2); vector-effect: non-scaling-stroke; stroke-width: 1.5; }
  .zm circle.camp:hover { fill-opacity: .85; }
  .zm text.lv { fill: var(--text); font-weight: 600; text-anchor: middle; pointer-events: none; }
  .zm .pt { vector-effect: non-scaling-stroke; stroke-width: 1.5; }
  .zm .pt:hover { stroke-width: 3; }
  .zm .ln { vector-effect: non-scaling-stroke; fill: none; }
  .zm text.tag { font-weight: 600; paint-order: stroke; stroke: var(--panel); stroke-width: 3px; stroke-linejoin: round; pointer-events: none; }
</style>
<script>
(function () {
  var data = $json, LAYERS_URL = $layers_url, svg = document.getElementById('zm'), cv = document.getElementById('zm-nav'), stage = document.getElementById('zm-stage');
  var NS = 'http://www.w3.org/2000/svg', L = null, ISL = {}, ISL_TOTAL = 1;
  // the layers: id, label, colour, on by default
  var DEFS = [
    ['lines', 'Map lines', '#8a8270', true], ['places', 'Place names', '#8a8270', true], ['camps', 'Camps', '#c9a227', true],
    ['navmesh', 'Navmesh', '#3fae5a', false], ['links', 'Off-mesh links', '#d6336c', false],
    ['zonelines', 'Zone lines', '#1f9d55', true], ['books', 'Books & teleport doors', '#7c4dff', true], ['lifts', 'Lifts', '#0aa6c2', true],
    ['locked', 'Locked doors', '#e8590c', true], ['doors', 'Other doors', '#868e96', false],
    ['ladders', 'Ladders', '#2b8a3e', true], ['jumps', 'Jumps', '#f08c00', true], ['swims', 'Swims', '#1c7ed6', true], ['teleports', 'Nav teleports', '#ae3ec9', true],
    ['boats', 'Boats', '#0b7285', true], ['spawns', 'Spawn points', '#495057', false],
    ['survey', 'Survey failures', '#e03131', false], ['stuck', 'Stuck spots (7 days)', '#c2255c', false],
    ['gap_bridge', 'Gaps: bridges', '#f03e3e', false], ['gap_poi', 'Gaps: near zone lines/spawns', '#fd7e14', false], ['gap_plain', 'Gaps: other', '#fab005', false],
    ['gap_outside', 'Gaps: outside the world', '#adb5bd', false], ['phantom', 'Phantom navmesh', '#9c36b5', false], ['water', 'Underwater floor', '#4dabf7', false],
    ['zlregions', 'Zone line regions', '#2f9e44', false]];
  var KEY = 'zm.layers.v1', on = {};
  var saved = {}; try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
  DEFS.forEach(function (d) { on[d[0]] = d[0] in saved ? !!saved[d[0]] : d[3]; });
  function save() { try { localStorage.setItem(KEY, JSON.stringify(on)); } catch (e) {} }
  function esc(s) { return String(s == null ? '' : s); }
  function el(name, attrs, parent) { var e = document.createElementNS(NS, name); for (var k in attrs) e.setAttribute(k, attrs[k]); (parent || svg).appendChild(e); return e; }
  function tip(e, text) { el('title', {}, e).textContent = text; return e; }
  function loc(p) { return '/loc ' + Math.round(-p[1]) + ', ' + Math.round(-p[0]) + ', ' + Math.round(p[2] || 0); }

  // the view: the client's lines and the camps set the frame
  var xs = [], ys = [];
  data.lines.forEach(function (l) { xs.push(l[0], l[2]); ys.push(l[1], l[3]); });
  data.camps.forEach(function (c) { xs.push(c.x); ys.push(c.y); });
  if (!xs.length) { xs = [-500, 500]; ys = [-500, 500]; }
  var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs), minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
  var pad = Math.max(maxX - minX, maxY - minY) * 0.03 + 20;
  var home = [minX - pad, minY - pad, maxX - minX + 2 * pad, maxY - minY + 2 * pad], view = home.slice(), size = Math.max(home[2], home[3]);

  var G = {};
  ['gap_bridge', 'gap_poi', 'gap_plain', 'gap_outside', 'phantom', 'water', 'zlregions', 'navlinks', 'lines', 'swims', 'ladders', 'jumps', 'teleports', 'boats', 'survey', 'stuck', 'spawns', 'doors', 'locked', 'lifts', 'books', 'zonelines', 'camps', 'places', 'links']
    .forEach(function (k) { G[k] = el('g', {}); });
  data.lines.forEach(function (l) { el('line', {x1: l[0], y1: l[1], x2: l[2], y2: l[3], 'class': 'ml'}, G.lines); });
  data.labels.forEach(function (p) { el('text', {x: p[0], y: p[1], 'class': 'place', 'data-fs': 0.012}, G.places).textContent = p[2]; });
  data.camps.forEach(function (c) {
    var r = size * (0.006 + Math.min(c.spawns || 1, 30) * 0.0006);
    var lv = c.levels.length ? (Math.min.apply(null, c.levels) + (Math.max.apply(null, c.levels) > Math.min.apply(null, c.levels) ? '–' + Math.max.apply(null, c.levels) : '')) : '';
    var g = el('g', {}, G.camps), dot = el('circle', {cx: c.x, cy: c.y, r: r, 'class': 'camp'}, g);
    tip(dot, 'Camp' + (lv ? ', levels ' + lv : '') + ' · ' + (c.spawns || 0) + ' spawns' + (c.respawn_s ? ', back in ' + Math.round(c.respawn_s / 60) + ' min' : '')
      + (c.mobs.length ? '\\n' + c.mobs.join(', ') : '') + '\\n/loc ' + Math.round(-c.y) + ', ' + Math.round(-c.x));
    if (lv) el('text', {x: c.x, y: c.y + r * 0.35, 'class': 'lv', 'font-size': r}, g).textContent = lv;
  });

  // a marker: a shape at p, its size in screen pixels (kept as the view zooms), z for the height slice
  var marks = [];
  function mark(group, p, shape, colour, px, text, label) {
    var e;
    if (shape === 'circle') e = el('circle', {cx: p[0], cy: p[1], r: 1, fill: colour, 'fill-opacity': .75, stroke: colour, 'class': 'pt'}, group);
    else if (shape === 'square') e = el('rect', {x: p[0], y: p[1], width: 1, height: 1, fill: colour, 'fill-opacity': .8, stroke: '#000', 'stroke-opacity': .35, 'class': 'pt'}, group);
    else if (shape === 'diamond') e = el('rect', {x: p[0], y: p[1], width: 1, height: 1, fill: colour, 'fill-opacity': .9, stroke: '#000', 'stroke-opacity': .5, 'class': 'pt'}, group);
    else e = el('path', {d: '', fill: 'none', stroke: colour, 'class': 'pt'}, group);  // a cross
    tip(e, text);
    var t = label ? el('text', {x: p[0], y: p[1], fill: colour, 'class': 'tag'}, group) : null;
    if (t) t.textContent = label;
    marks.push({e: e, t: t, p: p, shape: shape, px: px, z: p[2]});
  }
  function seg(group, a, b, colour, dash, text, z) {
    var e = el('line', {x1: a[0], y1: a[1], x2: b[0], y2: b[1], stroke: colour, 'stroke-width': 2, 'stroke-dasharray': dash || '', 'class': 'ln'}, group);
    if (text) tip(e, text);
    marks.push({e: e, line: true, z: z != null ? z : a[2], z2: b[2]});
    return e;
  }

  // the navmesh: polygons [island, z, x1, y1, ...] by island, one Path2D each (rebuilt when the height slice moves)
  var navPaths = null, navKey = null;
  var PALETTE = ['#e8590c', '#7048e8', '#1c7ed6', '#d6336c', '#0ca678', '#f59f00', '#ae3ec9', '#1098ad', '#e03131', '#74b816', '#4263eb', '#c2255c'];
  function islandColour(i) { return i === 0 ? '#3fae5a' : PALETTE[i % PALETTE.length]; }
  function zWin() { var s = document.getElementById('zm-slice'); if (!s.checked) return null; var c = +document.getElementById('zm-z').value, w = +document.getElementById('zm-zw').value || 40; return [c - w, c + w]; }
  function buildNav() {
    var zw = zWin(), key = zw ? zw.join(':') : 'all';
    if (navKey === key) return;
    navKey = key; navPaths = {};
    (L.navmesh.polys || []).forEach(function (p) {
      if (zw && (p[1] < zw[0] || p[1] > zw[1])) return;
      var k = p[0] === 0 ? 0 : 1 + p[0] % PALETTE.length, path = navPaths[k] || (navPaths[k] = new Path2D());
      path.moveTo(p[2], p[3]); for (var i = 4; i < p.length; i += 2) path.lineTo(p[i], p[i + 1]); path.closePath();
    });
  }
  // the view box as the svg fits it (preserveAspectRatio meet): map -> pixels
  function fit() { var r = stage.getBoundingClientRect(), s = Math.min(r.width / view[2], r.height / view[3]);
    return {w: r.width, h: r.height, s: s, ox: (r.width - view[2] * s) / 2, oy: (r.height - view[3] * s) / 2}; }
  function drawNav() {
    var f = fit(), dpr = window.devicePixelRatio || 1;
    if (cv.width !== Math.round(f.w * dpr) || cv.height !== Math.round(f.h * dpr)) { cv.width = Math.round(f.w * dpr); cv.height = Math.round(f.h * dpr); }
    var ctx = cv.getContext('2d'); ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
    if (L && L.navmesh && on.navmesh) {
      buildNav();
    ctx.setTransform(dpr * f.s, 0, 0, dpr * f.s, dpr * (f.ox - view[0] * f.s), dpr * (f.oy - view[1] * f.s));
    ctx.lineWidth = 1 / f.s;
      for (var k in navPaths) { var c = islandColour(+k); ctx.fillStyle = ctx.strokeStyle = c; ctx.globalAlpha = .22; ctx.fill(navPaths[k]); ctx.globalAlpha = .55; ctx.stroke(navPaths[k]); }
    }
    if (L && L.audit) drawAudit(ctx, f, dpr);  // (over the navmesh: the gaps are what's to be seen)
  }

  var AUDIT_COL = {bridge: '#f03e3e', poi: '#fd7e14', plain: '#fab005', outside: '#adb5bd', phantom: '#9c36b5', water: '#4dabf7', zoneline: '#2f9e44'};
  var AUDIT_ON = {bridge: 'gap_bridge', poi: 'gap_poi', plain: 'gap_plain', outside: 'gap_outside', phantom: 'phantom', water: 'water', zoneline: 'zlregions'};
  function auditCol(p) { return p.kind === 'zoneline' && (p.islands || []).indexOf(0) < 0 ? '#e03131' : AUDIT_COL[p.kind]; }
  function drawAudit(ctx, f, dpr) {
    var zw = zWin(), st = L.audit.step || 4;
    ctx.save(); ctx.setTransform(dpr * f.s, 0, 0, dpr * f.s, dpr * (f.ox - view[0] * f.s), dpr * (f.oy - view[1] * f.s));
    L.audit.patches.forEach(function (p) {
      if (!on[AUDIT_ON[p.kind]]) return;
      ctx.fillStyle = auditCol(p); ctx.globalAlpha = p.kind === 'zoneline' ? .45 : .7;
      for (var i = 0; i < p.c.length; i++) { var c = p.c[i]; if (zw && (c[2] < zw[0] || c[2] > zw[1])) continue; ctx.fillRect(c[0] - st / 2, c[1] - st / 2, st, st); }
    });
    ctx.restore();
  }
  function draw() {
    svg.setAttribute('viewBox', view.join(' '));
    var f = fit(), u = 1 / f.s, zw = zWin();  // u: one screen pixel in map units
    G.places.querySelectorAll('text').forEach(function (t) { t.setAttribute('font-size', 11 * u); });
    marks.forEach(function (m) {
      var hide = zw && m.z != null && (m.z < zw[0] || m.z > zw[1]) && (m.z2 == null || m.z2 < zw[0] || m.z2 > zw[1]);
      m.e.style.display = hide ? 'none' : ''; if (m.t) m.t.style.display = hide ? 'none' : '';
      if (hide || m.line) return;
      var r = m.px * u, x = m.p[0], y = m.p[1];
      if (m.shape === 'circle') m.e.setAttribute('r', r);
      else if (m.shape === 'square') { m.e.setAttribute('x', x - r); m.e.setAttribute('y', y - r); m.e.setAttribute('width', 2 * r); m.e.setAttribute('height', 2 * r); }
      else if (m.shape === 'diamond') { m.e.setAttribute('x', x - r); m.e.setAttribute('y', y - r); m.e.setAttribute('width', 2 * r); m.e.setAttribute('height', 2 * r);
        m.e.setAttribute('transform', 'rotate(45 ' + x + ' ' + y + ')'); }
      else m.e.setAttribute('d', 'M' + (x - r) + ' ' + (y - r) + 'L' + (x + r) + ' ' + (y + r) + 'M' + (x - r) + ' ' + (y + r) + 'L' + (x + r) + ' ' + (y - r));
      if (m.t) { m.t.setAttribute('x', x + r * 1.4); m.t.setAttribute('y', y + r * 0.5); m.t.setAttribute('font-size', 11 * u); }
    });
    drawNav();
  }
  function show() {
    var lg = document.getElementById('zm-navlegend'); lg.hidden = !(on.navmesh && L && L.navmesh);
    document.getElementById('zm-stucklegend').hidden = !(on.stuck && L && (L.stuck || []).length);
    document.getElementById('zm-auditlegend').hidden = !(L && L.audit && (on.gap_bridge || on.gap_poi || on.gap_plain || on.gap_outside || on.phantom || on.water || on.zlregions));
    for (var k in G) {
      var id = k === 'navlinks' ? 'links' : k;
      G[k].style.display = on[id] === false ? 'none' : '';
    }
    draw();
  }

  // the toggles, each with how many it has once the layers are in
  var counts = {};
  function toggles() {
    var box = document.getElementById('zm-toggles'); box.innerHTML = '';
    DEFS.forEach(function (d) {
      var lab = document.createElement('label'), cb = document.createElement('input'), sw = document.createElement('i'), n = counts[d[0]];
      cb.type = 'checkbox'; cb.checked = on[d[0]]; sw.style.background = d[2];
      lab.appendChild(cb); lab.appendChild(sw); lab.appendChild(document.createTextNode(d[1]));
      if (n != null) { var s = document.createElement('small'); s.textContent = ' ' + n; lab.appendChild(s); }
      lab.className = on[d[0]] ? '' : 'off';
      cb.onchange = function () { on[d[0]] = cb.checked; lab.className = cb.checked ? '' : 'off'; save(); show(); };
      box.appendChild(lab);
    });
  }
  counts.lines = data.lines.length; counts.places = data.labels.length; counts.camps = data.camps.length;
  toggles(); show();

  function addLayers(d) {
    L = d;
    var C = {}; DEFS.forEach(function (x) { C[x[0]] = x[2]; });
    (d.navmesh && d.navmesh.links || []).forEach(function (l) {
      seg(G.navlinks, [l[0], l[1], l[2]], [l[3], l[4], l[5]], C.links, '6 4', 'Off-mesh link (the navmesh joins these two points: a jump, a drop, a ladder)\\n' + loc([l[0], l[1], l[2]]) + ' → ' + loc([l[3], l[4], l[5]]));
    });
    (d.zonelines || []).forEach(function (z) {
      var ok = z.survey === 'OK', bad = z.survey && z.survey !== 'OK', col = ok ? '#1f9d55' : bad ? '#e03131' : '#868e96';
      mark(G.zonelines, z.at, 'diamond', col, 7, 'Zone line #' + z.number + ' to ' + (z.to_name || z.to || '?') + ' (' + z.to + ')\\n'
        + (ok ? 'the survey walked to it: the navmesh reaches it' : bad ? 'the survey could NOT walk to it (' + z.survey + ')' : 'not surveyed') + '\\n' + loc(z.at), '→ ' + (z.to_name || z.to));
    });
    var dk = {book: ['books', 'square', 6], lift: ['lifts', 'square', 5], locked: ['locked', 'square', 5], door: ['doors', 'square', 3.5]};
    (d.doors || []).forEach(function (o) {
      var k = dk[o.kind]; if (!k) return;
      var txt = (o.kind === 'book' ? 'Book / teleport door' : o.kind === 'lift' ? 'Lift' : o.kind === 'locked' ? 'Locked door' : 'Door') + ' #' + o.id + ' (' + o.name + ')'
        + (o.to ? '\\nto ' + o.to : '') + (o.key ? '\\nneeds the ' + (o.key_name || 'item ' + o.key) + ' (item ' + o.key + ')' : '')
        + (o.key_from && o.key_from.length ? '\\ndropped by ' + o.key_from.join(', ') : '') + (o.lockpick ? '\\nor picked: Pick Lock ' + o.lockpick + ', lockpicks on the cursor' : '') + '\\n' + loc(o.at);
      mark(G[k[0]], o.at, k[1], C[k[0]], k[2], txt, o.kind === 'book' ? '→ ' + o.to : o.kind === 'locked' ? 'locked' : '');
    });
    [['ladders', 'Ladder'], ['jumps', 'Jump'], ['teleports', 'Navmesh teleport']].forEach(function (x) {
      (d[x[0]] || []).forEach(function (j) {
        seg(G[x[0]], j.from, j.to, C[x[0]], x[0] === 'jumps' ? '3 3' : '', x[1] + ': ' + loc(j.from) + ' → ' + loc(j.to));
        mark(G[x[0]], j.from, 'circle', C[x[0]], 3, x[1] + ' (from)\\n' + loc(j.from));
      });
    });
    (d.swims || []).forEach(function (w) {
      for (var i = 1; i < w.points.length; i++) seg(G.swims, w.points[i - 1], w.points[i], C.swims, '', 'Swim (a way through water the navmesh adds)\\n' + loc(w.points[0]));
    });
    (d.boats || []).forEach(function (b) {
      if (b.box) { var r = el('rect', {x: b.box[0], y: b.box[2], width: b.box[1] - b.box[0], height: b.box[3] - b.box[2], fill: C.boats, 'fill-opacity': .15, stroke: C.boats,
        'stroke-width': 2, 'class': 'ln'}, G.boats); tip(r, 'Boat: walking into this box takes you to ' + b.to); marks.push({e: r, line: true, z: b.at[2]}); }
      mark(G.boats, b.at, 'diamond', C.boats, 7, 'Boat to ' + b.to + '\\n' + loc(b.at), '⛵ ' + b.to);
    });
    (d.spawns || []).forEach(function (s) {
      var lv = s.levels[0] === s.levels[1] ? s.levels[0] : s.levels[0] + '–' + s.levels[1];
      mark(G.spawns, s.at, 'circle', s.raid ? '#c92a2a' : C.spawns, 2.6, 'Spawn point: ' + s.names + '\\nlevel ' + lv + (s.respawn ? ' · respawn ' + Math.round(s.respawn / 60) + ' min' : '') + '\\n' + loc(s.at));
    });
    (d.survey || []).forEach(function (w) {
      if (w.from) seg(G.survey, w.from, w.at, C.survey, '2 4', 'Survey walk that failed (' + w.status + ')', w.at[2]);
      mark(G.survey, w.at, 'cross', C.survey, 5, 'Survey: couldn\'t walk to ' + w.kind + ' ' + (w.what || '') + ' (' + w.status + ')\\n' + loc(w.at) + (w.from ? '\\nfrom ' + loc(w.from) : ''));
    });
    (d.stuck || []).forEach(function (n) {
      if (n.to) seg(G.stuck, n.at, n.to, C.stuck, '1 4', n.who + ': wanted to get here', n.at[2]);
      mark(G.stuck, n.at, 'circle', C.stuck, 3.5, n.who + ': ' + n.kind + ' (' + new Date(n.t * 1000).toLocaleString() + ')\\n' + loc(n.at) + (n.to ? '\\nheading for ' + loc(n.to) : ''));
    });
    // the islands: each one's walkable area (the polygons' shoelace areas), for the legend and the hover
    ISL = {};
    var total = 0;
    (d.navmesh && d.navmesh.polys || []).forEach(function (p) {
      var a = 0, n = (p.length - 2) / 2;
      for (var i = 0; i < n; i++) { var j = (i + 1) % n; a += p[2 + 2 * i] * p[3 + 2 * j] - p[2 + 2 * j] * p[3 + 2 * i]; }
      a = Math.abs(a) / 2; total += a;
      var e = ISL[p[0]] || (ISL[p[0]] = {area: 0, polys: 0, zlo: Infinity, zhi: -Infinity}); e.area += a; e.polys++; if (p[1] < e.zlo) e.zlo = p[1]; if (p[1] > e.zhi) e.zhi = p[1];
    });
    ISL_TOTAL = total || 1;
    if (d.navmesh) {
      var ids = Object.keys(ISL).map(Number).sort(function (a, b) { return a - b; }), main = ISL[0], others = ids.length - 1;
      var tiny = ids.filter(function (i) { return i > 0 && ISL[i].area / ISL_TOTAL < 0.001; }).length;
      var html = '<b>The navmesh</b> is where a character can walk. Each separate piece of it (an <b>island</b>) has its own colour: a character can walk anywhere on '
        + 'its island but can\'t walk from one island to another. '
        + '<span class=\"sw\" style=\"background:' + islandColour(0) + ' !important\"></span><b>Green</b> is the main island, the biggest: ' + (main ? Math.round(100 * main.area / ISL_TOTAL) : 0)
        + '% of the walkable area; the zone lines, camps and books the leaders use should be on it. '
        + 'Every <b>other colour</b> is a piece not joined to the green (' + others + ' of them' + (tiny ? ', ' + tiny + ' tiny' : '') + '): a leader there is stuck unless a jump, ladder or '
        + 'off-mesh link (dashed pink) joins it. The colours repeat (' + PALETTE.length + ' of them), so two pieces of the same colour aren\'t joined either. '
        + 'With a height slice, only that floor is drawn. Hover the mesh to see which island a spot is on.';
      var big = ids.filter(function (i) { return i > 0; }).slice(0, 8);
      if (big.length) html += '<br><b>Largest other islands:</b> ' + big.map(function (i) { var e = ISL[i];
        return '<span class=\"isl\"><span class=\"sw\" style=\"background:' + islandColour(1 + i % PALETTE.length) + ' !important\"></span>#' + i + ': '
          + (100 * e.area / ISL_TOTAL).toFixed(1) + '%, z ' + Math.round(e.zlo) + (e.zhi - e.zlo > 2 ? ' to ' + Math.round(e.zhi) : '') + '</span>'; }).join('');
      document.getElementById('zm-navlegend').innerHTML = html;
    }
    // the audit's patches (tools/navaudit.py): a marker at each one's middle, its cells on the canvas
    var A = d.audit;
    if (A) {
      var KDESC = {bridge: 'floor the client has and the navmesh doesn\'t, touching navmesh islands ', poi: 'floor with no navmesh, with a zone line, spawn, camp or stuck spot in it',
        plain: 'floor with no navmesh', outside: 'floor with no navmesh touching none of it and nothing in it: out of the playable world', zoneline: 'the zone line\'s region (the server\'s water map): reached by island ', phantom: 'navmesh where the client has no standable floor (the server\'s collision map and the client disagree)', water: 'floor under deep water (left to the swims)'};
      A.patches.forEach(function (p) {
        var g = G[AUDIT_ON[p.kind]]; if (!g) return;
        mark(g, [p.x, p.y, p.z], 'circle', auditCol(p), p.kind === 'bridge' || p.kind === 'zoneline' ? 6 : 4,
          p.id + ' · ' + p.kind + ': ' + KDESC[p.kind] + (p.kind === 'bridge' || p.kind === 'zoneline' ? ((p.islands || []).length ? '#' + p.islands.join(', #') : 'none') + (p.islands && p.islands.indexOf(0) >= 0 ? ' (the main island among them)' : p.kind === 'zoneline' ? ' (NOT the main island: from the main area no walk gets to it)' : '') : '')
          + (p.kind === 'zoneline' ? ' · ' + p.covered + '% of its floor has navmesh' : '')
          + '\\n' + p.area + ' square units · z ' + p.zmin + (p.zmax - p.zmin > 2 ? ' to ' + p.zmax : '')
          + (p.pois && p.pois.length ? '\\nin it: ' + p.pois.slice(0, 8).join('; ') + (p.pois.length > 8 ? '; …' : '') : '') + '\\n' + loc([p.x, p.y, p.z]),
          p.kind === 'bridge' || p.kind === 'zoneline' ? p.id : '');
      });
      ['bridge', 'poi', 'plain', 'outside', 'phantom', 'water'].forEach(function (k) { counts[AUDIT_ON[k]] = (A.counts || {})[k] || 0; });
      counts.zlregions = A.patches.filter(function (p) { return p.kind === 'zoneline'; }).length;
      document.getElementById('zm-auditlegend').innerHTML = '<b>The geometry audit</b> (' + (A.when || '') + ') laid the client\'s own floors over the navmesh, every ' + A.step + ' units: '
        + A.covered_pct + '% of the floor a character could stand on has navmesh. What\'s left is drawn by kind: '
        + '<span class=\"sw\" style=\"background:#f03e3e !important\"></span><b>bridges</b>: floor with no navmesh touching two or more islands, so the likeliest place to join them (each marked with its id); '
        + '<span class=\"sw\" style=\"background:#fd7e14 !important\"></span><b>near points of interest</b>: a gap with a zone line, spawn point, camp or stuck spot in it; '
        + '<span class=\"sw\" style=\"background:#fab005 !important\"></span><b>other gaps</b>; '
        + '<span class=\"sw\" style=\"background:#9c36b5 !important\"></span><b>phantom navmesh</b>: navmesh where the client has no floor to stand on (in a wall, in the air, under the ground); '
        + '<span class=\"sw\" style=\"background:#4dabf7 !important\"></span><b>underwater floor</b>, which the navmesh leaves to the swims; '
        + '<span class=\"sw\" style=\"background:#adb5bd !important\"></span><b>outside the world</b>: floor touching no navmesh with nothing in it. '
        + '<b>Zone line regions</b> (the server\'s own, from its water map) show <span class=\"sw\" style=\"background:#2f9e44 !important\"></span>green when the main island reaches them and <span class=\"sw\" style=\"background:#e03131 !important\"></span>red when it doesn\'t (a walk from the main area can\'t get there). '
        + 'Gaps right beside walls and ledges are the navmesh\'s own margin and aren\'t shown. Hover a marker for its size, height and what\'s in it.';
    }
    // what the stuck spots' kinds mean (the server's walk notes, eqmq NavNote)
    var KIND = {not_connected: 'the start and the goal are on navmesh islands that aren\'t joined: no walk can link them',
      no_mesh_there: 'the goal has no navmesh under it (in a wall, under the floor, in the air)', no_mesh_here: 'the leader stood off the navmesh',
      off_mesh: 'the walk had to step off the navmesh to go on', straight_walk: 'no path, so it walked straight at the goal', stuck: 'it stopped moving (a wall, a ledge, a door)',
      too_deep: 'water deeper than its breath allowed', locked: 'a locked door it had no key for (picked, or the key fetched, now)', door_wont_open: 'a door that didn\'t open',
      cant_jump: 'a drop or jump it couldn\'t make', NO_PATH: 'no path found'};
    var kinds = {}; (d.stuck || []).forEach(function (n) { kinds[n.kind] = (kinds[n.kind] || 0) + 1; });
    document.getElementById('zm-stucklegend').innerHTML = '<b>Stuck spots</b> are where a leader\'s walk failed this past week. Each <span class=\"sw\" style=\"background:#c2255c !important;border-radius:50%\"></span><b>dot</b> is where the leader was when it failed; the <b>dotted line</b> from it runs to the point it was trying to reach. '
      + 'Dots that bunch up mark a spot the navmesh needs fixing; long lines usually mean the goal and the leader were on different islands. Hover a dot for who, when and why. By kind:<br>'
      + Object.keys(kinds).sort(function (a, b) { return kinds[b] - kinds[a]; }).map(function (k) { return '<b>' + k + '</b> ' + kinds[k] + ': ' + (KIND[k] || k); }).join('<br>');
    counts.navmesh = d.navmesh ? (d.navmesh.polys || []).length + ' polys, ' + (d.navmesh.islands || 0) + ' islands' : 'none';
    counts.links = d.navmesh ? (d.navmesh.links || []).length : 0;
    ['zonelines', 'ladders', 'jumps', 'swims', 'teleports', 'boats', 'spawns', 'survey', 'stuck'].forEach(function (k) { counts[k] = (d[k] || []).length; });
    ['books', 'lifts', 'locked', 'doors'].forEach(function (k) { counts[k] = (d.doors || []).filter(function (o) { return (o.kind === 'book' ? 'books' : o.kind === 'lift' ? 'lifts' : o.kind === 'locked' ? 'locked' : 'doors') === k; }).length; });
    // the height slice spans the navmesh's floors
    var zlo = Infinity, zhi = -Infinity;  // (a loop: 57,000 polygons spread into Math.min is past what a browser takes)
    (d.navmesh && d.navmesh.polys || []).forEach(function (p) { if (p[1] < zlo) zlo = p[1]; if (p[1] > zhi) zhi = p[1]; });
    if (zlo <= zhi) { var lo = Math.floor(zlo), hi = Math.ceil(zhi), zr = document.getElementById('zm-z');
      zr.min = lo; zr.max = hi; zr.value = Math.round((lo + hi) / 2); document.getElementById('zm-zval').textContent = 'z ' + zr.value + ' (' + lo + ' to ' + hi + ')'; }
    var bad = (d.zonelines || []).filter(function (z) { return z.survey && z.survey !== 'OK'; }).length;
    document.getElementById('zm-status').textContent = (d.navmesh ? (d.navmesh.islands || 0) + ' navmesh islands' : 'no navmesh') + ' · ' + (d.zonelines || []).length + ' zone lines'
      + (bad ? ', ' + bad + ' the survey couldn\'t reach' : '') + (d.survey_when ? ' · surveyed ' + d.survey_when : '');
    toggles(); show();
  }
  fetch(LAYERS_URL).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }).then(addLayers)
    .catch(function (e) { document.getElementById('zm-status').textContent = 'couldn\'t load the navigation layers (' + e.message + ')'; });

  var slice = document.getElementById('zm-slice'), zr = document.getElementById('zm-z');
  slice.onchange = function () { zr.disabled = !slice.checked; navKey = null; draw(); };
  zr.oninput = function () { document.getElementById('zm-zval').textContent = 'z ' + zr.value + ' (' + zr.min + ' to ' + zr.max + ')'; navKey = null; draw(); };
  document.getElementById('zm-zw').oninput = function () { navKey = null; draw(); };

  function point(e) { var r = stage.getBoundingClientRect(), f = fit();
    return [view[0] + (e.clientX - r.left - f.ox) / f.s, view[1] + (e.clientY - r.top - f.oy) / f.s, 1 / f.s]; }
  svg.addEventListener('wheel', function (e) {
    e.preventDefault();
    var p = point(e), k = e.deltaY > 0 ? 1.2 : 1 / 1.2;
    view = [p[0] - (p[0] - view[0]) * k, p[1] - (p[1] - view[1]) * k, view[2] * k, view[3] * k]; draw();
  }, {passive: false});
  var drag = null;
  svg.addEventListener('mousedown', function (e) { drag = point(e); svg.style.cursor = 'grabbing'; });
  window.addEventListener('mouseup', function () { drag = null; svg.style.cursor = ''; });
  svg.addEventListener('mousemove', function (e) {
    if (!drag) return;
    var r = stage.getBoundingClientRect(), f = fit();
    view[0] = drag[0] - (e.clientX - r.left - f.ox) / f.s; view[1] = drag[1] - (e.clientY - r.top - f.oy) / f.s; draw();
  });
  svg.addEventListener('dblclick', function () { view = home.slice(); draw(); });
  // the island under the pointer (the navmesh drawn): a point-in-polygon over its polygons, a few times a second
  var hoverBox = document.getElementById('zm-hover'), hoverAt = 0;
  function inPoly(x, y, p) {
    var n = (p.length - 2) / 2, c = false;
    for (var i = 0, j = n - 1; i < n; j = i++) { var xi = p[2 + 2 * i], yi = p[3 + 2 * i], xj = p[2 + 2 * j], yj = p[3 + 2 * j];
      if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) c = !c; }
    return c;
  }
  svg.addEventListener('mousemove', function (e) {
    if (drag || !on.navmesh || !L || !L.navmesh) { hoverBox.hidden = true; return; }
    var now = performance.now(); if (now - hoverAt < 120) return; hoverAt = now;
    var pt = point(e), zw = zWin(), hit = null;
    var polys = L.navmesh.polys;
    for (var k = 0; k < polys.length; k++) { var p = polys[k]; if (zw && (p[1] < zw[0] || p[1] > zw[1])) continue; if (inPoly(pt[0], pt[1], p)) { hit = p; if (p[0] === 0) break; } }
    if (!hit) { hoverBox.hidden = true; return; }
    var isl = ISL[hit[0]] || {area: 0}, r = stage.getBoundingClientRect();
    hoverBox.innerHTML = (hit[0] === 0 ? '<b>Main island</b> (green): ' : '<b>Island #' + hit[0] + '</b>: <b>not joined to the main island</b>, ')
      + (100 * isl.area / ISL_TOTAL).toFixed(isl.area / ISL_TOTAL < 0.01 ? 2 : 1) + '% of the walkable area' + '<br>here: z ' + Math.round(hit[1]) + ' · /loc ' + Math.round(-pt[1]) + ', ' + Math.round(-pt[0]);
    hoverBox.style.left = Math.min(r.width - 310, e.clientX - r.left + 14) + 'px'; hoverBox.style.top = (e.clientY - r.top + 14) + 'px'; hoverBox.hidden = false;
  });
  svg.addEventListener('mouseleave', function () { hoverBox.hidden = true; });
  window.addEventListener('resize', draw);
  var wrap = document.querySelector('.zm-wrap'), fullBtn = document.getElementById('zm-full');
  var home_parent = wrap.parentNode, home_next = wrap.nextSibling;
  fullBtn.onclick = function () {
    // (up to the page's top while full: inside the Codex's content box its header stays over it, whatever the z-index)
    var f = wrap.classList.toggle('full');
    if (f) document.body.appendChild(wrap); else home_parent.insertBefore(wrap, home_next);
    document.body.style.overflow = f ? 'hidden' : '';
    fullBtn.textContent = f ? '✕ Close full screen' : '⤢ Full screen'; setTimeout(draw, 30);
  };
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && wrap.classList.contains('full')) fullBtn.onclick(); });
})();
</script>";
}
