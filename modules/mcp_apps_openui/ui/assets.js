import postcss from 'postcss';
import valueParser from 'postcss-value-parser';
/** Only Drupal-issued attached asset IDs can be requested. */
export async function materializePreview(host, session, payload) {
  const specs = payload.assets, loaded = new Map(), urls = new Map();
  async function read(id) {
    const spec = specs[id];
    if (!spec || spec.size > 6 * 1024 * 1024) throw new Error('Invalid preview asset.');
    const chunks = [];
    for (let offset = 0; offset < spec.size;) {
      const result = await host.call('tool_api__component_composer_asset', { session_id: session, asset_id: id, offset });
      const base64 = result._meta?.ui?.base64;
      if (typeof base64 !== 'string') throw new Error('The host did not forward app asset metadata.');
      const bytes = Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
      if (!bytes.length || bytes.length > 196608 || offset + bytes.length > spec.size) throw new Error('Invalid asset chunk.');
      chunks.push(bytes); offset += bytes.length;
    }
    loaded.set(id, new Blob(chunks, { type: spec.mime }));
  }
  const ids = Object.keys(specs);
  for (const id of ids) await read(id);
  const building = new Set();
  async function resolve(id) {
    if (urls.has(id)) return urls.get(id);
    if (building.has(id)) throw new Error('Cyclic stylesheet imports are unsupported.');
    building.add(id);
    const spec = specs[id]; let blob = loaded.get(id);
    if (spec.mime === 'text/css') {
      const replacements = new Map();
      for (const [source, target] of Object.entries(spec.urls)) replacements.set(source, target.startsWith('mcp-asset:') ? await resolve(target.slice(10)) : target);
      const css = postcss.parse(await blob.text(), { from: undefined });
      const rewrite = (value) => {
        const parsed = valueParser(value);
        parsed.walk((node) => {
          if (node.type === 'function' && node.value.toLowerCase() === 'url') {
            const source = valueParser.stringify(node.nodes).replace(/^['"]|['"]$/g, '');
            if (replacements.has(source)) node.nodes = [{ type: 'string', quote: '"', value: replacements.get(source) }];
          }
          if (node.type === 'string' && replacements.has(node.value)) node.value = replacements.get(node.value);
        });
        return parsed.toString();
      };
      css.walkDecls((decl) => { decl.value = rewrite(decl.value); });
      css.walkAtRules('import', (rule) => { rule.params = rewrite(rule.params); });
      blob = new Blob([css.toString()], { type: 'text/css' });
    }
    // Data URLs work inside opaque sandbox origins, including WebKit hosts.
    const bytes = new Uint8Array(await blob.arrayBuffer());
    let binary = '';
    for (let offset = 0; offset < bytes.length; offset += 32768) binary += String.fromCharCode(...bytes.subarray(offset, offset + 32768));
    const url = 'data:' + blob.type + ';base64,' + btoa(binary);
    urls.set(id, url); building.delete(id); return url;
  }
  try {
    for (const id of ids) await resolve(id);
    const html = payload.html.replace(/mcp-asset:([a-f0-9]{64})/g, (_, id) => {
      if (!urls.has(id)) throw new Error('Missing attached preview asset.');
      return urls.get(id);
    });
    return { html, dispose: () => urls.clear() };
  } catch (error) { urls.clear(); throw error; }
}
