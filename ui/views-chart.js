import { $, element, host, showError } from './common.js';

const colors = ['#4478e8', '#18aa93', '#ef9b50', '#a276de', '#e06087', '#647c91'];
const ns = 'http://www.w3.org/2000/svg';
const state = { data: null, visible: new Set(), mode: 'line', month: null, busy: false };
const bridge = host('views-chart', 'Drupal Views: Content Pulse', receive);
const svg = (tag, attrs = {}, text) => { const node = document.createElementNS(ns, tag); for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value); if (text !== undefined) node.textContent = text; return node; };
const activeSeries = () => state.data.series.filter((series) => state.visible.has(series.id));
const totals = () => state.data.labels.map((_, index) => activeSeries().reduce((sum, series) => sum + series.values[index], 0));

function receive(result) {
  if (result.isError) { showError(new Error(result.content?.[0]?.text || 'View unavailable.')); return; }
  const data = result.structuredContent;
  if (data?.app !== 'views-chart' || !Array.isArray(data.series) || !Array.isArray(data.labels)) return;
  const old = state.data;
  state.data = data;
  state.visible = new Set(data.series.filter((series) => !old || state.visible.has(series.id) || !old.series.some((item) => item.id === series.id)).map((series) => series.id));
  state.month = null;
  document.body.dataset.ready = 'true'; $('error').hidden = true;
  $('months').value = data.filters.months; $('scope').value = data.filters.scope;
  $('author').replaceChildren(new Option('All authors', '0'), ...data.authors.map((author) => new Option(author.name, author.id)));
  if (![...$('author').options].some((option) => Number(option.value) === data.filters.author)) $('author').append(new Option(`Author #${data.filters.author}`, data.filters.author));
  $('author').value = data.filters.author;
  render();
}

function context() {
  bridge.context({ filters: state.data.filters, visible_series: activeSeries().map((series) => ({ name: series.name, values: series.values })), labels: state.data.labels, focused_month: state.month === null ? null : state.data.labels[state.month] });
}

function render() {
  const data = state.data; const sums = totals(); const total = sums.reduce((a, b) => a + b, 0);
  const peak = Math.max(0, ...sums); const peakIndex = sums.indexOf(peak);
  $('total').textContent = total.toLocaleString();
  $('total-foot').textContent = `${data.filters.scope === 'demo' ? 'Sample drafts' : 'Accessible site content'} · ${activeSeries().length} types`;
  $('peak').textContent = peak ? data.labels[peakIndex].split(' ')[0] : '—';
  $('peak-foot').textContent = peak ? `${peak} records · ${data.labels[peakIndex]}` : 'No records in this selection';
  $('average').textContent = (total / data.labels.length).toFixed(1);
  $('range').textContent = `${data.labels[0]} — ${data.labels.at(-1)} · UTC`;
  $('source-note').textContent = `Source: ${data.view.id} · ${data.filters.scope === 'demo' ? 'Sample unpublished content' : 'Accessible site content'}${data.limited ? ' · First 2,000 View rows' : ''} · UTC`;
  $('line-mode').setAttribute('aria-pressed', String(state.mode === 'line')); $('bar-mode').setAttribute('aria-pressed', String(state.mode === 'bar'));
  $('legend').replaceChildren();
  data.series.forEach((series, index) => {
    const button = element('button'); button.type = 'button'; button.dataset.series = series.id;
    button.style.setProperty('--series-color', colors[index % colors.length]); button.setAttribute('aria-pressed', String(state.visible.has(series.id)));
    button.append(element('span', 'swatch'), document.createTextNode(series.name));
    button.addEventListener('click', () => { state.visible.has(series.id) ? state.visible.delete(series.id) : state.visible.add(series.id); render(); context(); $('legend').querySelector(`[data-series="${series.id}"]`)?.focus(); });
    $('legend').append(button);
  });
  renderChart(); renderInsights(sums, total); renderTable();
  $('share-chart').disabled = !bridge.connected || !bridge.app?.getHostCapabilities()?.message;
}

function renderChart() {
  const data = state.data; const series = activeSeries(); const width = 740; const height = 290; const left = 42; const right = 18; const top = 24; const bottom = 24;
  const chartWidth = width - left - right; const chartHeight = height - top - bottom;
  const max = Math.max(1, ...series.flatMap((item) => item.values)); const ceiling = Math.max(4, Math.ceil(max / 4) * 4);
  const x = (index) => left + (index + .5) * chartWidth / data.labels.length;
  const y = (value) => top + chartHeight * (1 - value / ceiling);
  const canvas = svg('svg', { viewBox: `0 0 ${width} ${height}`, role: 'img', 'aria-labelledby': 'chart-title chart-description' });
  canvas.append(svg('title', { id: 'chart-title' }, `Content creation ${state.mode} chart`), svg('desc', { id: 'chart-description' }, `${data.labels.length} months, ${series.length} visible content types. Exact values are available in the data table below.`));
  const defs = svg('defs');
  canvas.append(defs);
  for (let i = 0; i <= 4; i++) { const value = ceiling * i / 4; canvas.append(svg('line', { x1: left, x2: width - right, y1: y(value), y2: y(value), stroke: '#e8edf5', 'stroke-dasharray': i ? '3 5' : 'none' }), svg('text', { x: left - 10, y: y(value) + 4, 'text-anchor': 'end', fill: '#8391a7', 'font-size': 10 }, value)); }
  if (state.month !== null) canvas.append(svg('rect', { x: x(state.month) - chartWidth / data.labels.length / 2, y: top, width: chartWidth / data.labels.length, height: chartHeight, fill: '#f1f5fe' }));
  series.forEach((item, visibleIndex) => {
    const color = colors[data.series.indexOf(item) % colors.length];
    if (state.mode === 'line') {
      const gradient = svg('linearGradient', { id: `area-${visibleIndex}`, x1: '0', y1: '0', x2: '0', y2: '1' });
      gradient.append(svg('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .14 }), svg('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 })); defs.append(gradient);
      const points = item.values.map((value, index) => `${x(index)},${y(value)}`).join(' ');
      canvas.append(svg('polygon', { points: `${x(0)},${y(0)} ${points} ${x(data.labels.length - 1)},${y(0)}`, fill: `url(#area-${visibleIndex})` }));
      canvas.append(svg('polyline', { points, fill: 'none', stroke: color, 'stroke-width': 3, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
      item.values.forEach((value, index) => { const point = svg('circle', { cx: x(index), cy: y(value), r: state.month === index ? 5 : 3.5, fill: color, stroke: '#fff', 'stroke-width': 2 }); point.append(svg('title', {}, `${item.name} · ${data.labels[index]}: ${value}`)); canvas.append(point); });
    } else {
      const groupWidth = chartWidth / data.labels.length * .68; const barWidth = groupWidth / Math.max(1, series.length);
      item.values.forEach((value, index) => { const bar = svg('rect', { x: x(index) - groupWidth / 2 + visibleIndex * barWidth, y: y(value), width: Math.max(2, barWidth - 3), height: y(0) - y(value), rx: 3, fill: color }); bar.append(svg('title', {}, `${item.name} · ${data.labels[index]}: ${value}`)); canvas.append(bar); });
    }
  });
  if (!series.length) canvas.append(svg('text', { x: width / 2, y: height / 2, fill: '#65738b', 'font-size': 14, 'text-anchor': 'middle' }, 'Choose a content type below to explore.'));
  $('chart').replaceChildren(canvas);
  $('month-buttons').replaceChildren();
  data.labels.forEach((label, index) => { const button = element('button', '', label.split(' ')[0]); button.type = 'button'; button.setAttribute('aria-label', `Explore ${label}`); button.setAttribute('aria-pressed', String(state.month === index)); button.addEventListener('click', () => { state.month = state.month === index ? null : index; render(); context(); $('month-buttons').children[index]?.focus(); }); $('month-buttons').append(button); });
}

function renderInsights(sums, total) {
  const peak = Math.max(0, ...sums); const index = sums.indexOf(peak);
  $('insight-title').textContent = state.month === null ? 'The bigger picture' : state.data.labels[state.month];
  $('insight-copy').textContent = state.month !== null ? `${sums[state.month]} records were created in this month across your visible content types.` : total ? `${state.data.labels[index]} leads with ${peak} records. Toggle a type to see what drives the pattern.` : 'No records match this selection. Enable a type or change the Drupal filters.';
  $('breakdown').replaceChildren(element('span', 'muted', state.month === null ? 'Visible content mix' : 'This month'));
  activeSeries().forEach((item) => {
    const value = state.month === null ? item.values.reduce((a, b) => a + b, 0) : item.values[state.month];
    const denominator = state.month === null ? total : sums[state.month];
    const row = element('div', 'breakdown-row'); row.append(element('span', '', item.name), element('strong', '', value));
    const bar = element('div', 'mini-bar'); const fill = element('span'); fill.style.width = `${denominator ? value / denominator * 100 : 0}%`; fill.style.setProperty('--series-color', colors[state.data.series.indexOf(item) % colors.length]); bar.append(fill);
    $('breakdown').append(row, bar);
  });
}

function renderTable() {
  const table = element('table'); const caption = element('caption', 'muted', 'Content created per month (UTC)'); table.append(caption);
  const head = element('thead'); const row = element('tr'); for (const name of ['Month', ...activeSeries().map((item) => item.name), 'Total']) { const th = element('th', '', name); th.scope = 'col'; row.append(th); } head.append(row); table.append(head);
  const body = element('tbody'); const sums = totals();
  state.data.labels.forEach((label, index) => { const tr = element('tr'); const th = element('th', '', label); th.scope = 'row'; tr.append(th); for (const value of [...activeSeries().map((item) => item.values[index]), sums[index]]) tr.append(element('td', '', value)); body.append(tr); }); table.append(body); $('data-table').replaceChildren(table);
}

for (const mode of ['line', 'bar']) $(`${mode}-mode`).addEventListener('click', () => { state.mode = mode; if (state.data) render(); });
$('filter-form').addEventListener('submit', async (event) => {
  event.preventDefault(); if (state.busy) return;
  state.busy = true; for (const control of $('filter-form').elements) control.disabled = true;
  try { await bridge.call({ months: Number($('months').value), scope: $('scope').value, author: Number($('author').value) }); }
  catch (error) { showError(error); }
  finally { state.busy = false; for (const control of $('filter-form').elements) control.disabled = bridge.preview; }
});
$('share-chart').addEventListener('click', async () => {
  $('share-chart').disabled = true;
  try { await bridge.app.sendMessage({ role: 'user', content: [{ type: 'text', text: `Help me interpret this Drupal View selection. These are ${state.data.filters.scope === 'demo' ? 'sample draft records' : 'accessible site records'}.\n${JSON.stringify({ view: state.data.view, filters: state.data.filters, labels: state.data.labels, series: activeSeries(), focused_month: state.month === null ? null : state.data.labels[state.month] })}` }] }); $('share-feedback').textContent = 'Current chart selection sent to the conversation.'; }
  catch (error) { showError(error); }
  finally { $('share-chart').disabled = false; }
});
document.addEventListener('host-connected', () => { $('apply-filters').disabled = false; if (state.data) render(); });
$('apply-filters').disabled = true; $('share-chart').disabled = true;
if (bridge.preview) for (const control of $('filter-form').elements) control.disabled = true;
bridge.start();
