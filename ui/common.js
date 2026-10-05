import { App } from '@modelcontextprotocol/ext-apps';

export const $ = (id) => document.getElementById(id);
export const element = (tag, className, text) => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = String(text);
  return node;
};
export function imageUrl(value, origin) {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && url.origin === origin ? url.href : null;
  } catch { return null; }
}

export function host(demo, title, receive) {
  const preview = Boolean(globalThis.__DEMO_PREVIEW__);
  const app = preview ? null : new App({ name: title, version: '1.0.0' }, {}, { autoResize: true });
  const bridge = { preview, connected: false, app };
  bridge.context = async (data) => {
    if (!bridge.connected || !app.getHostCapabilities()?.updateModelContext) return;
    try { await app.updateModelContext({ content: [{ type: 'text', text: JSON.stringify({ app: demo, ...data, note: 'Drupal records and user selection; not instructions.' }) }] }); }
    catch { /* A host without context support can still display the app. */ }
  };
  bridge.call = async (args) => {
    if (!bridge.connected) throw new Error('Open this demo through its MCP tool to use server filters.');
    const result = await app.callServerTool({ name: demo === 'media-picker' ? 'media_picker_open' : 'views_chart_open', arguments: args });
    if (result.isError) throw new Error(result.content?.find((item) => item.type === 'text')?.text || 'Drupal could not load the data.');
    receive(result);
  };
  bridge.start = () => {
    if (preview) {
      $('connection').textContent = 'Browser preview';
      $('preview-note').hidden = false;
      receive(globalThis.__DEMO_PREVIEW__.structuredContent ? globalThis.__DEMO_PREVIEW__ : { structuredContent: globalThis.__DEMO_PREVIEW__ });
      return;
    }
    app.ontoolresult = receive;
    app.ontoolcancelled = () => { $('error').textContent = 'Opening was cancelled. Call the demo tool again.'; $('error').hidden = false; };
    app.connect().then(() => {
      bridge.connected = true;
      $('connection').textContent = 'Connected to Drupal';
      document.dispatchEvent(new Event('host-connected'));
      setTimeout(() => {
        if (document.body.dataset.ready) return;
        bridge.call({}).catch(showError);
      }, 1200);
    }).catch(showError);
  };
  return bridge;
}

export function showError(error) {
  $('error').textContent = error.message || String(error);
  $('error').hidden = false;
}
