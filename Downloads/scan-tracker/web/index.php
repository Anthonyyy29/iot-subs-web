<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Tracker — ESP32 HC-SR04</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0d0b08;
    --bg-panel: #161209;
    --bg-panel-2: #1c1710;
    --amber: #ffb300;
    --amber-bright: #ffc94d;
    --amber-dim: #8a5a00;
    --amber-faint: #3a2c10;
    --grid-line: #2a2115;
    --text-muted: #9c8a6e;
    --text-faint: #5f5340;
    --danger: #ff5c4d;
    --mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--amber);
    font-family: var(--mono);
    min-height: 100vh;
    background-image:
      radial-gradient(circle at 50% 0%, rgba(255,179,0,0.05), transparent 60%),
      repeating-linear-gradient(0deg, rgba(255,255,255,0.012) 0px, rgba(255,255,255,0.012) 1px, transparent 1px, transparent 3px);
  }

  .wrap {
    max-width: 1080px;
    margin: 0 auto;
    padding: 28px 20px 60px;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 1px solid var(--grid-line);
    padding-bottom: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
  }

  .eyebrow {
    font-size: 11px;
    letter-spacing: 0.18em;
    color: var(--text-faint);
    text-transform: uppercase;
    margin: 0 0 6px;
  }

  h1 {
    margin: 0;
    font-size: 24px;
    letter-spacing: 0.06em;
    font-weight: 700;
    color: var(--amber-bright);
    text-shadow: 0 0 12px rgba(255,179,0,0.25);
  }

  .status-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    padding: 6px 12px;
    border: 1px solid var(--grid-line);
    border-radius: 3px;
    background: var(--bg-panel);
  }

  .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--text-faint);
  }
  .dot.live { background: var(--amber); box-shadow: 0 0 8px var(--amber); animation: pulse 1.6s ease-in-out infinite; }
  .dot.dead { background: var(--danger); }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
  }

  .grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
  }

  @media (max-width: 720px) {
    .grid { grid-template-columns: 1fr; }
  }

  .panel {
    background: var(--bg-panel);
    border: 1px solid var(--grid-line);
    border-radius: 4px;
    padding: 18px;
  }

  .panel-label {
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--text-faint);
    margin: 0 0 14px;
  }

  /* --- Radar / scope gauge --- */
  .scope {
    position: relative;
    width: 260px;
    height: 260px;
    margin: 0 auto;
    border-radius: 50%;
    background: radial-gradient(circle, var(--bg-panel-2) 0%, #0a0805 100%);
    border: 1px solid var(--amber-faint);
    overflow: hidden;
  }

  .scope::before {
    content: "";
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(0deg, rgba(255,179,0,0.025) 0px, transparent 1px, transparent 3px);
    pointer-events: none;
  }

  .ring {
    position: absolute;
    border: 1px solid var(--amber-faint);
    border-radius: 50%;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
  }

  .crosshair {
    position: absolute;
    background: var(--amber-faint);
    top: 50%; left: 50%;
  }
  .crosshair.h { width: 100%; height: 1px; transform: translate(-50%, -50%); }
  .crosshair.v { width: 1px; height: 100%; transform: translate(-50%, -50%); }

  .sweep {
    position: absolute;
    inset: 0;
    background: conic-gradient(from 0deg, rgba(255,179,0,0.35), transparent 22%, transparent 100%);
    animation: rotate 3.2s linear infinite;
    mix-blend-mode: screen;
  }

  @keyframes rotate {
    to { transform: rotate(360deg); }
  }

  .blip {
    position: absolute;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--amber-bright);
    box-shadow: 0 0 10px 3px rgba(255,201,77,0.7);
    left: 50%;
    transform: translate(-50%, -50%);
    transition: top 0.4s ease;
  }

  .blip.danger {
    background: var(--danger);
    box-shadow: 0 0 10px 3px rgba(255,92,77,0.7);
  }

  .readout {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
  }

  .readout .value {
    font-size: 34px;
    font-weight: 700;
    color: var(--amber-bright);
    text-shadow: 0 0 14px rgba(255,179,0,0.4);
  }
  .readout .unit {
    font-size: 11px;
    color: var(--text-muted);
    letter-spacing: 0.14em;
    margin-top: 2px;
  }

  .scale-note {
    text-align: center;
    font-size: 10px;
    color: var(--text-faint);
    margin-top: 12px;
    letter-spacing: 0.08em;
  }

  /* --- Chart --- */
  canvas#chart {
    width: 100%;
    height: 160px;
    display: block;
  }

  /* --- Table --- */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
  }

  th {
    text-align: left;
    color: var(--text-faint);
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 10.5px;
    padding: 6px 10px;
    border-bottom: 1px solid var(--grid-line);
  }

  td {
    padding: 7px 10px;
    border-bottom: 1px solid var(--grid-line);
    color: var(--text-muted);
  }

  tr:first-child td { color: var(--amber-bright); }

  td.ok { color: var(--amber); }
  td.bad { color: var(--danger); }

  .table-wrap {
    max-height: 320px;
    overflow-y: auto;
    margin-top: 4px;
  }

  .table-wrap::-webkit-scrollbar { width: 6px; }
  .table-wrap::-webkit-scrollbar-thumb { background: var(--amber-faint); border-radius: 3px; }

  .empty {
    color: var(--text-faint);
    font-size: 12px;
    padding: 20px 0;
    text-align: center;
  }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <div>
      <p class="eyebrow">ESP32 · HC-SR04 · MQTT LIVE FEED</p>
      <h1>SCAN TRACKER</h1>
    </div>
    <div class="status-pill">
      <span class="dot" id="statusDot"></span>
      <span id="statusText">MENYAMBUNG...</span>
    </div>
  </header>

  <div class="grid">
    <div class="panel">
      <p class="panel-label">Jarak Saat Ini</p>
      <div class="scope">
        <div class="ring" style="width:96%; height:96%;"></div>
        <div class="ring" style="width:70%; height:70%;"></div>
        <div class="ring" style="width:44%; height:44%;"></div>
        <div class="ring" style="width:18%; height:18%;"></div>
        <div class="crosshair h"></div>
        <div class="crosshair v"></div>
        <div class="sweep"></div>
        <div class="blip" id="blip" style="top:50%;"></div>
        <div class="readout">
          <div class="value" id="readoutValue">--</div>
          <div class="unit">CM</div>
        </div>
      </div>
      <p class="scale-note">SKALA 0–400 CM</p>
    </div>

    <div class="panel">
      <p class="panel-label">Riwayat Jarak (30 Pembacaan Terakhir)</p>
      <canvas id="chart" width="700" height="160"></canvas>
    </div>
  </div>

  <div class="panel" style="margin-top:20px;">
    <p class="panel-label">Log Pembacaan</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Jarak (cm)</th>
            <th>Status</th>
            <th>IP Device</th>
          </tr>
        </thead>
        <tbody id="logBody">
          <tr><td colspan="4" class="empty">Menunggu data pertama...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
const MAX_RANGE_CM = 400;
const POLL_MS = 3000;
const STALE_MS = 15000;

let lastFetchAt = null;

async function fetchData() {
  try {
    const res = await fetch('api.php?limit=50', { cache: 'no-store' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Gagal ambil data');
    lastFetchAt = Date.now();
    render(json.data);
  } catch (e) {
    console.error(e);
  }
  updateStatus();
}

function updateStatus() {
  const dot = document.getElementById('statusDot');
  const text = document.getElementById('statusText');
  const isLive = lastFetchAt && (Date.now() - lastFetchAt) < STALE_MS;
  dot.className = 'dot ' + (isLive ? 'live' : 'dead');
  text.textContent = isLive ? 'TERHUBUNG' : 'TERPUTUS';
}

function render(rows) {
  if (!rows.length) return;

  const latest = rows[0];
  const value = latest.jarak_cm;
  const isBad = latest.status !== 'ok' || value === null;

  document.getElementById('readoutValue').textContent = isBad ? '—' : Number(value).toFixed(1);

  const blip = document.getElementById('blip');
  if (isBad) {
    blip.classList.add('danger');
    blip.style.top = '50%';
  } else {
    blip.classList.remove('danger');
    const frac = Math.min(value / MAX_RANGE_CM, 1);
    const topPct = 50 - frac * 46; // 46% = radius menuju tepi lingkaran
    blip.style.top = topPct + '%';
  }

  renderTable(rows);
  renderChart(rows);
}

function renderTable(rows) {
  const body = document.getElementById('logBody');
  body.innerHTML = rows.slice(0, 20).map(r => {
    const bad = r.status !== 'ok' || r.jarak_cm === null;
    const cls = bad ? 'bad' : 'ok';
    const jarak = r.jarak_cm === null ? '—' : Number(r.jarak_cm).toFixed(1);
    return `<tr>
      <td>${formatTime(r.created_at)}</td>
      <td class="${cls}">${jarak}</td>
      <td class="${cls}">${r.status}</td>
      <td>${r.ip || '—'}</td>
    </tr>`;
  }).join('');
}

function formatTime(ts) {
  const d = new Date(ts.replace(' ', 'T'));
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function renderChart(rows) {
  const canvas = document.getElementById('chart');
  const ctx = canvas.getContext('2d');
  const w = canvas.width, h = canvas.height;
  ctx.clearRect(0, 0, w, h);

  const data = rows.slice(0, 30).reverse();
  const values = data.map(r => r.jarak_cm !== null ? Number(r.jarak_cm) : null);
  const valid = values.filter(v => v !== null);
  if (!valid.length) return;

  const maxV = Math.max(...valid, 10);
  const minV = 0;
  const padding = 12;

  // grid horizontal
  ctx.strokeStyle = '#2a2115';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 3; i++) {
    const y = padding + (h - padding * 2) * (i / 3);
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(w, y);
    ctx.stroke();
  }

  // garis data
  ctx.strokeStyle = '#ffb300';
  ctx.lineWidth = 2;
  ctx.beginPath();
  values.forEach((v, i) => {
    if (v === null) return;
    const x = (w - padding * 2) * (i / (values.length - 1 || 1)) + padding;
    const y = h - padding - (h - padding * 2) * (v - minV) / (maxV - minV || 1);
    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
  });
  ctx.stroke();

  // titik-titik
  ctx.fillStyle = '#ffc94d';
  values.forEach((v, i) => {
    if (v === null) return;
    const x = (w - padding * 2) * (i / (values.length - 1 || 1)) + padding;
    const y = h - padding - (h - padding * 2) * (v - minV) / (maxV - minV || 1);
    ctx.beginPath();
    ctx.arc(x, y, 2.5, 0, Math.PI * 2);
    ctx.fill();
  });
}

fetchData();
setInterval(fetchData, POLL_MS);
setInterval(updateStatus, 2000);
</script>
</body>
</html>
