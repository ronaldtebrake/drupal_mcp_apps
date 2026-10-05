import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { TextEncoder, TextDecoder } from 'node:util';
import { JSDOM, VirtualConsole } from 'jsdom';

const waitFor = async (predicate, label) => {
  for (let i = 0; i < 100; i++) {
    if (predicate()) return;
    await new Promise((resolve) => setTimeout(resolve, 10));
  }
  throw new Error(`Timed out: ${label}`);
};

async function setup(demo) {
  const html = await readFile(new URL(`../dist/${demo}.html`, import.meta.url), 'utf8');
  const fixture = JSON.parse(await readFile(join(tmpdir(), `${demo}-fixture.json`), 'utf8'));
  const requests = []; const errors = []; const logs = new VirtualConsole();
  logs.on('jsdomError', (error) => errors.push(error));
  const dom = new JSDOM(html, {
    url: 'https://mcp-app.invalid/', runScripts: 'dangerously', pretendToBeVisual: true, virtualConsole: logs,
    beforeParse(w) {
      w.TextEncoder = TextEncoder; w.TextDecoder = TextDecoder; w.structuredClone = structuredClone;
      w.ResizeObserver = class { observe() {} disconnect() {} };
      const reply = (value) => queueMicrotask(() => w.dispatchEvent(new w.MessageEvent('message', { data: value, source: w.parent })));
      w.postMessage = (request) => {
        requests.push(request);
        if (request.method === 'ui/initialize') {
          reply({ jsonrpc: '2.0', id: request.id, result: { protocolVersion: request.params.protocolVersion, hostInfo: { name: 'Demo smoke host', version: '1' }, hostCapabilities: { serverTools: {}, message: { text: {} }, updateModelContext: { text: {} } }, hostContext: { theme: 'light', displayMode: 'inline' } } });
        } else if (request.method === 'ui/notifications/initialized') {
          reply({ jsonrpc: '2.0', method: 'ui/notifications/tool-result', params: fixture });
        } else if (request.method === 'tools/call') {
          const result = structuredClone(fixture);
          if (request.params.name === 'tool_api__media_picker_save_hero') {
            const args = request.params.arguments;
            const post = fixture.structuredContent.posts.find((item) => item.id === args.node_id);
            result.structuredContent = { app: 'media-picker-hero-saved', post: { ...post, hero: { id: args.media_id, name: 'New hero' }, alt: args.alt, revision: 'new-revision' }, message: 'Hero saved as a new revision.' };
            reply({ jsonrpc: '2.0', id: request.id, result }); return;
          }
          result.structuredContent.query = request.params.arguments.query;
          result.structuredContent.media = fixture.structuredContent.media.filter((item) => item.name.toLowerCase().includes(request.params.arguments.query.toLowerCase()));
          reply({ jsonrpc: '2.0', id: request.id, result });
        } else if (request.id !== undefined) {
          reply({ jsonrpc: '2.0', id: request.id, result: {} });
        }
      };
    },
  });
  await waitFor(() => dom.window.document.body.dataset.ready && dom.window.document.body.dataset.connection === 'connected', `${demo} SDK handshake`);
  return { dom, document: dom.window.document, fixture, requests, errors, html };
}

const media = await setup('media-picker');
try {
  const { dom, document: doc, fixture, requests, errors } = media;
  assert.equal(doc.querySelectorAll('.media-card').length, fixture.structuredContent.media.length);
  assert([...doc.querySelectorAll('.media-card img')].every((image) => image.src.startsWith('data:image/')), 'MCP images must display without network access to DDEV.');
  const initialHero = fixture.structuredContent.posts[0].hero.id;
  assert.equal(doc.querySelector('#article-image img').src, fixture._meta['drupal/media-picker'].heroThumbnails[initialHero], 'Article preview uses its Drupal-cropped hero derivative.');
  assert(requests.some((request) => request.method === 'ui/initialize'));
  const item = fixture.structuredContent.media[0];
  doc.querySelector(`.media-card[data-id="${item.id}"]`).click();
  assert.equal(doc.querySelector('#article-image img').src, fixture._meta['drupal/media-picker'].heroThumbnails[item.id], 'Proposed hero uses the same website crop.');
  assert.equal(doc.querySelector('#selection-detail').hidden, false);
  assert.equal(doc.querySelector('#selection-id').textContent, `Media #${item.id}`);
  assert.equal(doc.querySelector('#use-media').disabled, false);
  const alt = 'My accessible hero image description';
  doc.querySelector('#proposed-alt').value = alt;
  doc.querySelector('#proposed-alt').dispatchEvent(new dom.window.Event('input', { bubbles: true }));
  assert(!doc.querySelector('#article-select'), 'The workflow cannot switch articles.');
  assert(!requests.some((request) => request.method === 'tools/call'), 'Selecting and previewing must not save content.');
  assert.equal(doc.querySelector('#article-image img').alt, alt);
  doc.querySelector('#show-current').click();
  assert.equal(doc.querySelector('#show-current').getAttribute('aria-pressed'), 'true');
  doc.querySelector('#show-proposed').click();
  doc.querySelector('#use-media').click();
  await waitFor(() => doc.querySelector('#selection-feedback').textContent.includes('saved'), 'hero save');
  const save = requests.find((request) => request.method === 'tools/call' && request.params.name === 'tool_api__media_picker_save_hero');
  assert.equal(save.params.arguments.alt, alt);
  assert.equal(save.params.arguments.media_id, item.id);
  assert.equal(save.params.arguments.node_id, fixture.structuredContent.posts[0].id);
  assert(requests.some((request) => request.method === 'ui/update-model-context'));
  assert.equal(fixture.structuredContent.media[0].alt, item.alt, 'Proposed alt never modifies the original record.');
  doc.querySelector('#search').value = 'Rotterdam';
  doc.querySelector('#search').dispatchEvent(new dom.window.Event('input', { bubbles: true }));
  assert.equal(doc.querySelectorAll('.media-card').length, 4);
  doc.querySelector('#search-form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
  await waitFor(() => requests.some((request) => request.method === 'tools/call'), 'media server search');
  await waitFor(() => doc.querySelector('#result-count').textContent.includes('matching') && !doc.querySelector('#search-drupal').disabled, 'media search completion');
  assert.equal(requests.find((request) => request.method === 'tools/call' && request.params.name === 'tool_api__media_picker_open').params.arguments.query, 'Rotterdam');
  assert.equal(errors.length, 0, errors.map((error) => error.message).join('\n'));
  console.log('PASS: Media SDK handshake, inline images, article preview, current/proposed toggle, explicit one-click save and server search.');
} finally { media.dom.window.close(); }

const data = structuredClone(media.fixture.structuredContent);
data.media[0].name = '<img src=x onerror=alert(1)>';
data.media[0].thumbnail = 'javascript:alert(1)';
const dom = new JSDOM(media.html, { url: data.origin, runScripts: 'dangerously', beforeParse(w) { w.__DEMO_PREVIEW__ = data; w.TextEncoder = TextEncoder; w.TextDecoder = TextDecoder; } });
try {
  const doc = dom.window.document;
  assert.equal(doc.querySelector('#preview-note').hidden, false);
  assert.equal(doc.querySelector('.card-copy strong').textContent, data.media[0].name);
  assert.equal(doc.querySelector('.card-copy strong img'), null);
  assert.equal(doc.querySelector('.media-card').querySelector('img'), null);
  doc.querySelector('.media-card').click();
  assert.equal(doc.querySelector('#use-media').disabled, true);
  console.log('PASS: Media picker browser preview stays read-only; untrusted content cannot inject markup or unsafe URLs.');
} finally { dom.window.close(); }
