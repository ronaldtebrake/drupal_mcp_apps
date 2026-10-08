import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { JSDOM, VirtualConsole } from 'jsdom';
import { makeLibrary, parseComposition, toProgram, updateProp, flatten } from '../modules/mcp_apps_openui/ui/library.js';
import { serializeCalls } from '../ui/host.js';

const order = [];
const queued = serializeCalls(async (value) => {
  order.push(value);
  await new Promise((resolve) => setTimeout(resolve, 10));
  if (value === 'denied') throw new Error('Denied');
  return value;
});
const calls = await Promise.allSettled([queued('first'), queued('denied'), queued('last')]);
assert.deepEqual(order, ['first', 'denied', 'last']);
assert.equal(calls[1].status, 'rejected');
assert.equal(calls[2].value, 'last', 'A rejected call must not block later calls.');

const fixture = JSON.parse(await readFile(process.env.MCP_APPS_UI_FIXTURE || '/tmp/mcp-composer-fixture.json', 'utf8'));
const library = makeLibrary(() => null);
const source = toProgram(fixture.boot.ui.tree, library);
assert.equal(toProgram(parseComposition(source, library), library), source);
assert.throws(() => parseComposition('root = UnknownComponent()', library));
const requests = [], errors = [];
let rejectPreview = false;
let activeCalls = 0, maximumActiveCalls = 0;
const logs = new VirtualConsole();
logs.on('jsdomError', (error) => errors.push(error));
const waitFor = async (predicate, label) => {
  for (let attempt = 0; attempt < 200; attempt++) {
    if (predicate()) return;
    await new Promise((resolve) => setTimeout(resolve, 25));
  }
  throw new Error('Timed out: ' + label);
};
const dom = new JSDOM(await readFile(new URL('../modules/mcp_apps_openui/dist/composer.html', import.meta.url), 'utf8'), {
  url: 'https://mcp-app.invalid/', runScripts: 'dangerously', pretendToBeVisual: true, virtualConsole: logs,
  beforeParse(window) {
    window.TextEncoder = TextEncoder; window.TextDecoder = TextDecoder; window.structuredClone = structuredClone;
    window.Blob = Blob;
    window.ResizeObserver = class { observe() {} disconnect() {} };
    const reply = (value) => queueMicrotask(() => window.dispatchEvent(new window.MessageEvent('message', { data: value, source: window.parent })));
    window.postMessage = (request) => {
      requests.push(request);
      if (request.method === 'ui/initialize') {
        reply({ jsonrpc: '2.0', id: request.id, result: { protocolVersion: request.params.protocolVersion, hostInfo: { name: 'Test host', version: '1' }, hostCapabilities: { serverTools: {}, updateModelContext: { text: {} } }, hostContext: { theme: 'light', displayMode: 'inline' } } });
      } else if (request.method === 'ui/notifications/initialized') {
        reply({ jsonrpc: '2.0', method: 'ui/notifications/tool-result', params: { structuredContent: { data: fixture.boot.data }, _meta: { ui: fixture.boot.ui } } });
      } else if (request.method === 'tools/call') {
        activeCalls++;
        maximumActiveCalls = Math.max(maximumActiveCalls, activeCalls);
        let result;
        const args = request.params.arguments;
        if (request.params.name.endsWith('component_composer_preview')) {
          result = rejectPreview ? { isError: true, content: [{ type: 'text', text: 'Rejected composition for testing.' }] } : { structuredContent: { data: fixture.preview.data }, _meta: { ui: fixture.preview.ui } };
        } else if (request.params.name.endsWith('component_composer_asset')) {
          const bytes = Buffer.from(fixture.bytes[args.asset_id], 'base64').subarray(args.offset, args.offset + 196608);
          result = { structuredContent: { data: { bytes: bytes.length } }, _meta: { ui: { base64: bytes.toString('base64') } } };
        } else if (request.params.name.endsWith('composer_media_search')) {
          result = { structuredContent: { data: fixture.media.data }, _meta: { ui: fixture.media.ui } };
        } else throw new Error('Unexpected tool: ' + request.params.name);
        setTimeout(() => {
          activeCalls--;
          reply({ jsonrpc: '2.0', id: request.id, result });
        }, 20);
      } else if (request.id !== undefined) reply({ jsonrpc: '2.0', id: request.id, result: {} });
    };
  },
});
try {
  const document = dom.window.document;
  await waitFor(() => document.querySelector('iframe')?.getAttribute('srcdoc'), 'SDK handshake and native preview');
  const frame = document.querySelector('iframe');
  assert.equal(frame.getAttribute('sandbox'), 'allow-scripts');
  assert(frame.getAttribute('srcdoc').includes('data:text/css;base64,'));
  assert(frame.getAttribute('srcdoc').includes('data:image/jpeg;base64,'));
  assert.equal(document.querySelectorAll('.component-row').length, flatten(fixture.boot.ui.tree).length);
  assert(requests.some((request) => request.method === 'ui/initialize'));
  assert(requests.some((request) => request.method === 'ui/update-model-context'));
  [...document.querySelectorAll('button')].find((button) => button.textContent === 'Mobile').click();
  await waitFor(() => document.querySelector('.preview-stage.mobile'), 'mobile width');
  document.querySelector('.image-choice').click();
  await waitFor(() => document.querySelectorAll('.image-grid button').length, 'Media selector');
  assert([...document.querySelectorAll('.image-grid img')].every((image) => image.src.startsWith('data:image/')));
  document.querySelector('.image-grid button').click();
  await waitFor(() => !document.querySelector('.media-dialog'), 'image selection');
  const previous = frame.getAttribute('srcdoc');
  rejectPreview = true;
  const changed = updateProp(fixture.boot.ui.tree, [0], 'heading_text', 'A different heading');
  [...document.querySelectorAll('button')].find((button) => button.textContent === 'OpenUI source').click();
  await waitFor(() => document.querySelector('.source-dialog textarea'), 'source editor');
  const textarea = document.querySelector('.source-dialog textarea');
  Object.getOwnPropertyDescriptor(dom.window.HTMLTextAreaElement.prototype, 'value').set.call(textarea, toProgram(changed, library));
  textarea.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
  textarea.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
  [...document.querySelectorAll('button')].find((button) => button.textContent === 'Preview composition').click();
  await waitFor(() => document.querySelector('[role="alert"]')?.textContent.includes('Rejected composition'), 'failed server preview');
  assert.equal(frame.getAttribute('srcdoc'), previous, 'Keep the last valid preview on errors.');
  assert(requests.filter((request) => request.method === 'tools/call').every((request) => /component_composer_(preview|asset)$|composer_media_search$/.test(request.params.name)));
  assert.equal(errors.length, 0, errors.map((error) => error.message).join('\n'));
  assert.equal(maximumActiveCalls, 1, 'Tool calls must share the MCP session sequentially.');
  console.log('PASS: official OpenUI parser, SDK handshake, serialized calls and error recovery, local assets, nested components, widths, Media selection and last-valid-preview behavior.');
} finally { dom.window.close(); }
