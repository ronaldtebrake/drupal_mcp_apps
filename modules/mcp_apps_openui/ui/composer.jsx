import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Renderer } from '@openuidev/react-lang';
import { createHost, serializeCalls, toolProvider } from '../../../ui/host.js';
import { makeLibrary, parseComposition, toProgram, flatten, updateProp } from './library.js';
import { materializePreview } from './assets.js';
const apiNames = ['tool_api__component_composer_preview', 'tool_api__component_composer_asset', 'tool_api__composer_media_search'];
const browser = window.__DRUPAL_BROWSER_PREVIEW__;
let receive, previewResources, cancelled = false, sequence = 0;
const host = browser ? {
  call: serializeCalls(async (name, args) => {
    if (!apiNames.includes(name)) throw new Error('Unsupported preview operation.');
    const response = await fetch(browser.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': browser.token }, body: JSON.stringify({ name: name.replace(/^tool_api__/, ''), arguments: args }) });
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || 'Drupal refused the preview.');
    return result;
  }), context: async () => {},
} : createHost({
  name: 'Drupal OpenUI composer', receive: (result) => receive?.(result),
  error: (error) => window.dispatchEvent(new CustomEvent('composer-error', { detail: error.message })),
  cancel: () => { cancelled = true; sequence++; window.dispatchEvent(new CustomEvent('composer-error', { detail: 'The host cancelled this composition.' })); },
});
const provider = toolProvider(host, apiNames), library = makeLibrary(Editor);
const ComposerContext = React.createContext(null);
function Shell() {
  const [boot, setBoot] = useState(null), [program, setProgram] = useState(''), [error, setError] = useState('');
  receive = (result) => {
    const ui = result._meta?.ui;
    if (!ui?.session_id || !ui.catalog) { setError('The host did not forward composer metadata. Refresh the connection and reopen the app.'); return; }
    const source = ui.program || toProgram(ui.tree, library);
    try { parseComposition(source, library); cancelled = false; sequence++; setBoot(ui); setProgram(source); setError(''); } catch (error) { setError(error.message); }
  };
  useEffect(() => {
    const report = (event) => setError(event.detail);
    window.addEventListener('composer-error', report);
    if (browser) receive(browser.result); else host.connect();
    return () => { window.removeEventListener('composer-error', report); previewResources?.dispose(); };
  }, []);
  return <ComposerContext.Provider value={{ boot, error, setError, edit: (tree) => setProgram(toProgram(tree, library)) }}>
    {!boot ? <div className="waiting"><strong>Drupal component composer</strong><p>{error || 'Connecting to the MCP Apps host…'}</p></div> : <Renderer library={library} response={program} toolProvider={provider} onError={(error) => setError(error.message || String(error))} />}
  </ComposerContext.Provider>;
}
function Editor({ tree }) {
  const { boot, error, setError, edit } = React.useContext(ComposerContext);
  const [selected, setSelected] = useState(''), [width, setWidth] = useState('desktop'), [html, setHtml] = useState(''), [busy, setBusy] = useState(true);
  const [query, setQuery] = useState(boot.media_query || ''), [media, setMedia] = useState([]), [thumbnails, setThumbnails] = useState({}), [mediaTarget, setMediaTarget] = useState(null);
  const [source, setSource] = useState(false), [sourceText, setSourceText] = useState('');
  const rows = flatten(tree), row = rows.find((entry) => JSON.stringify(entry.path) === selected) || rows.find((entry) => entry.node.component === boot.initial_component) || rows[0];
  const schema = row && boot.catalog[row.node.component], serialized = JSON.stringify(tree);
  useEffect(() => {
    const ticket = ++sequence; setBusy(true);
    const timer = setTimeout(async () => {
      try {
        if (cancelled) return;
        const result = await provider.tool_api__component_composer_preview({ session_id: boot.session_id, composition: serialized });
        if (ticket !== sequence) return;
        const ui = result._meta?.ui;
        if (!ui?.html || !ui.assets) throw new Error('The host did not forward Drupal preview metadata.');
        const next = await materializePreview(host, boot.session_id, ui);
        if (ticket !== sequence) { next.dispose(); return; }
        const previous = previewResources; previewResources = next; setHtml(next.html);
        setTimeout(() => previous?.dispose(), 2000); setError('');
        await host.context({ message: 'Drupal preview updated. Nothing has been saved.', composition: tree });
      } catch (error) {
        if (ticket === sequence) { setError(error.message); await host.context({ error: error.message }).catch(() => {}); }
      } finally { if (ticket === sequence) setBusy(false); }
    }, 400);
    return () => clearTimeout(timer);
  }, [serialized, boot.session_id]);
  useEffect(() => {
    if (!boot.media_tool) return;
    let alive = true;
    const timer = setTimeout(async () => {
      try {
        const result = await provider.tool_api__composer_media_search({ query });
        if (alive) { setMedia(result.structuredContent?.data?.media || []); setThumbnails(result._meta?.ui?.thumbnails || {}); }
      } catch (error) { if (alive) setError(error.message); }
    }, 200);
    return () => { alive = false; clearTimeout(timer); };
  }, [query, boot.session_id]);
  const change = (name, value) => edit(updateProp(tree, row.path, name, value));
  return <div className="composer">
    <header className="top"><div><strong>Explore your Drupal components</strong><span>OpenUI · Native Drupal rendering</span></div><button className="plain" onClick={() => { setSource(!source); setSourceText(toProgram(tree, library)); }}>OpenUI source</button></header>
    <div className="workspace">
      <aside className="inspector"><div className="panel-title">COMPONENTS <span>{rows.length}</span></div>
        <nav aria-label="Composition components">{rows.map(({ node, path, depth }) => <button key={JSON.stringify(path)} className={'component-row ' + (JSON.stringify(path) === JSON.stringify(row?.path) ? 'selected' : '')} style={{ paddingLeft: 12 + depth * 10 }} onClick={() => { setSelected(JSON.stringify(path)); setMediaTarget(null); }}><span className="component-dot" />{boot.catalog[node.component]?.name || node.component.split(':')[1]}</button>)}</nav>
        {row && <section className="properties"><h2>{schema?.name || row.node.component}</h2>{Object.entries(schema?.props?.properties || {}).map(([name, field]) => {
          const value = row.node.props[name];
          if ((field.$ref || field.id)?.endsWith('/image') && boot.media_tool) return <div className="field" key={name}><span>{field.title || name}</span><button className="image-choice" onClick={() => setMediaTarget({ path: row.path, name })}>{thumbnails[value?.media_id] && <img src={thumbnails[value.media_id]} alt="" />}<span>{media.find((item) => item.id === value?.media_id)?.name || 'Choose image'}<small>Browse Drupal Media</small></span></button></div>;
          if (field.enum) return <label className="field" key={name}>{field.title || name}<select value={value === undefined ? '' : String(value)} onChange={(event) => change(name, event.target.value === '' ? undefined : field.enum.find((item) => String(item) === event.target.value))}><option value="">Default</option>{field.enum.map((item) => <option key={String(item)} value={String(item)}>{field['meta:enum']?.[item] || String(item)}</option>)}</select></label>;
          if (field.type === 'boolean') return <label className="check" key={name}><input type="checkbox" checked={Boolean(value)} onChange={(event) => change(name, event.target.checked)} />{field.title || name}</label>;
          if (['number', 'integer'].includes(field.type)) return <label className="field" key={name}>{field.title || name}<input type="number" value={value ?? ''} onChange={(event) => change(name, event.target.value === '' ? undefined : Number(event.target.value))} /></label>;
          if (field.type === 'string') return <label className="field" key={name}>{field.title || name}<textarea rows={name.includes('text') ? 3 : 1} value={value ?? ''} onChange={(event) => change(name, event.target.value)} /></label>;
          return <div className="field" key={name}><span>{field.title || name}</span><small>Set this structured prop in OpenUI source.</small></div>;
        })}</section>}
      </aside>
      <section className="preview-pane" aria-label="Drupal-rendered preview">
        <div className="preview-toolbar"><span className={'status ' + (error ? 'invalid' : '')}><i />{error ? 'Preview needs attention' : busy ? 'Rendering in Drupal…' : 'Rendered by Drupal'}</span><div className="segmented"><button aria-pressed={width === 'desktop'} onClick={() => setWidth('desktop')}>Desktop</button><button aria-pressed={width === 'mobile'} onClick={() => setWidth('mobile')}>Mobile</button></div></div>
        {error && <div className="error" role="alert">{error}<small>Your last successful preview remains visible.</small></div>}
        <div className={'preview-stage ' + width}>{html ? <iframe title="Drupal component composition" sandbox="allow-scripts" srcDoc={html} /> : <div className="placeholder">Preparing your Drupal components…</div>}</div>
        <footer className="preview-footer"><span>Existing templates. Existing styles.</span><span>Preview only · no content changes</span></footer>
      </section>
    </div>
    {mediaTarget && <div className="modal-backdrop"><section className="media-dialog" role="dialog" aria-modal="true" aria-label="Choose Drupal image"><header><div><h2>Find the right image</h2><p>From your Drupal Media library</p></div><button aria-label="Close image library" onClick={() => setMediaTarget(null)}>×</button></header><input autoFocus type="search" aria-label="Search image library" placeholder="Search images…" value={query} onChange={(event) => setQuery(event.target.value)} /><div className="image-grid">{media.map((item) => <button key={item.id} onClick={() => { edit(updateProp(tree, mediaTarget.path, mediaTarget.name, { media_id: item.id })); setMediaTarget(null); }}><img src={thumbnails[item.id]} alt={item.alt} /><strong>{item.name}</strong><small>{item.credit}</small></button>)}</div>{media.length === 0 && <p>No accessible images found.</p>}</section></div>}
    {source && <div className="modal-backdrop"><section className="source-dialog" role="dialog" aria-modal="true" aria-label="OpenUI composition source"><header><h2>OpenUI composition</h2><button aria-label="Close source" onClick={() => setSource(false)}>×</button></header><p>References your installed Drupal components. Their markup stays in Drupal.</p><textarea aria-label="OpenUI source" value={sourceText} onChange={(event) => setSourceText(event.target.value)} /><button className="primary" onClick={() => { try { edit(parseComposition(sourceText, library)); setSource(false); } catch (error) { setError(error.message); } }}>Preview composition</button></section></div>}
  </div>;
}
createRoot(document.getElementById('app')).render(<Shell />);
