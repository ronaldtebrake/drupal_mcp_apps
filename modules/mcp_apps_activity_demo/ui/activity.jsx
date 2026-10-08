import React, {useEffect, useRef, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {Renderer} from '@openuidev/react-lang';
import {createHost, serializeCalls, toolProvider} from '../../../ui/host.js';
import {makeLibrary, parseComposition, toProgram} from '../../mcp_apps_openui/ui/library.js';
import {materializePreview} from '../../mcp_apps_openui/ui/assets.js';
const names = ['tool_api__activity_dashboard_filter','tool_api__component_composer_preview','tool_api__component_composer_asset'];
const browser = window.__DRUPAL_BROWSER_PREVIEW__;
let receive, sequence = 0, cancelled = false, resources;
const host = browser ? {
  call:serializeCalls(async(name,args)=>{
    if(!names.includes(name)) throw new Error('Unsupported activity operation.');
    const response=await fetch(browser.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':browser.token},body:JSON.stringify({name:name.replace(/^tool_api__/,''),arguments:args})});
    const result=await response.json();
    if(!response.ok || result.error) throw new Error(result.error || 'Drupal refused the request.');
    return result;
  }),context:async()=>{},openLink:async url=>window.open(url,'_blank','noopener')
} : createHost({
  name:'Editorial pulse',receive:result=>receive?.(result),
  error:error=>window.dispatchEvent(new CustomEvent('activity-error',{detail:error.message})),
  cancel:()=>{cancelled=true;sequence++;window.dispatchEvent(new CustomEvent('activity-error',{detail:'The host cancelled this dashboard.'}));}
});
const provider=toolProvider(host,names), library=makeLibrary(Preview), Context=React.createContext(null);
function PeriodFilter({days,range,disabled,onApply}){
  const presets=[7,14,30];
  const [custom,setCustom]=useState(!presets.includes(days)),[draft,setDraft]=useState(String(days));
  useEffect(()=>{setDraft(String(days));setCustom(!presets.includes(days));},[days]);
  return <form className="activity-period" onSubmit={event=>{
    event.preventDefault();
    if(event.currentTarget.checkValidity()) onApply(Number(draft));
  }}>
    <select aria-label="Period" disabled={disabled} value={custom?'custom':days} onChange={event=>{
      const value=event.target.value;
      if(value==='custom') setCustom(true);
      else{
        if(Number(value)===days) setCustom(false);
        onApply(Number(value));
      }
    }}>
      {presets.map(value=><option key={value} value={value}>Last {value} days</option>)}
      <option value="custom">Custom period…</option>
    </select>
    {custom&&<><input aria-label="Number of days" type="number" min={range.min} max={range.max} step="1" required disabled={disabled} value={draft} onChange={event=>setDraft(event.target.value)} title={`Choose ${range.min}–${range.max} days`} /><span>days</span><button type="submit" disabled={disabled}>Apply</button></>}
  </form>;
}
function Shell(){
  const [boot,setBoot]=useState(null),[program,setProgram]=useState(''),[error,setError]=useState(''),[filtering,setFiltering]=useState(false);
  receive=result=>{
    const ui=result._meta?.ui;
    if(!ui?.session_id || !Array.isArray(ui.tree)){setError('The host did not forward dashboard metadata. Refresh the connection and reopen the app.');return;}
    try{const source=toProgram(ui.tree,library);parseComposition(source,library);cancelled=false;setBoot(ui);setProgram(source);setError('');}catch(error){setError(error.message);}
  };
  useEffect(()=>{
    const report=event=>setError(event.detail);window.addEventListener('activity-error',report);
    if(browser) receive(browser.result);else host.connect();
    return()=>{window.removeEventListener('activity-error',report);resources?.dispose();};
  },[]);
  const filter=async(days,section)=>{
    setFiltering(true);
    try{
      const result=await provider.tool_api__activity_dashboard_filter({session_id:boot.session_id,days,section});
      if(!cancelled) receive(result);
    }catch(error){setError(error.message);}finally{setFiltering(false);}
  };
  return <Context.Provider value={{boot,error,setError,program}}>
    {!boot?<div className="activity-loading">{error || 'Connecting to the MCP Apps host…'}</div>:<>
      <header className="activity-toolbar">
        <div><strong>{boot.title || 'Editorial pulse'}</strong><small>{boot.theme_label || boot.theme} · Drupal components · OpenUI</small></div>
        <div className="activity-filters">
          <PeriodFilter days={boot.days} range={boot.day_range || {min:1,max:30}} disabled={filtering || cancelled} onApply={days=>filter(days,boot.section)}/>
          <select aria-label="Section" disabled={filtering || cancelled} value={boot.section} onChange={event=>filter(boot.days,event.target.value)}><option value="all">All sections</option><option value="guides">Guides</option><option value="culture">Culture</option></select>
          <button disabled={filtering || cancelled} aria-label="Refresh dashboard" onClick={()=>filter(boot.days,boot.section)}>↻</button>
        </div>
      </header>
      {error&&<div className="activity-error" role="alert">{error} Your last successful dashboard remains visible.</div>}
      <Renderer library={library} response={program} toolProvider={provider} onError={error=>setError(error.message || String(error))}/>
    </>}
  </Context.Provider>;
}
function Preview({tree}){
  const {boot,program,setError}=React.useContext(Context), [html,setHtml]=useState(''),[styles,setStyles]=useState([]),[busy,setBusy]=useState(true),[mobile,setMobile]=useState(false),[height,setHeight]=useState(1500);
  const frame=useRef(null), serialized=JSON.stringify(tree);
  useEffect(()=>{
    const ticket=++sequence;setBusy(true);
    (async()=>{
      try{
        if(cancelled) return;
        const result=await provider.tool_api__component_composer_preview({session_id:boot.session_id,composition:serialized});
        if(ticket!==sequence) return;
        if(!result._meta?.ui?.html) throw new Error('The host did not forward native Drupal HTML.');
        const next=await materializePreview(host,boot.session_id,result._meta.ui);
        if(ticket!==sequence){next.dispose();return;}
        // Reuse the transported Drupal CSS and fonts for the compact app controls.
        const document=new DOMParser().parseFromString(next.html,'text/html');
        setStyles([...document.head.querySelectorAll('link[rel="stylesheet"]')].map(link=>link.getAttribute('href')).filter(href=>href?.startsWith('data:text/css;base64,')));
        const previous=resources;resources=next;setHtml(next.html);setTimeout(()=>previous?.dispose(),2000);setError('');
        await host.context({message:'Editorial view uses fictional demo data; no content was changed.',theme:boot.theme,view:boot.view,days:boot.days,section:boot.section,components:tree.map(node=>node.component)});
      }catch(error){if(ticket===sequence){setError(error.message);await host.context({error:error.message}).catch(()=>{});}}
      finally{if(ticket===sequence) setBusy(false);}
    })();
  },[serialized,boot.session_id,boot.theme]);
  useEffect(()=>{
    const resize=event=>{
      if(event.source===frame.current?.contentWindow && event.data?.type==='mcp-apps-activity-height' && Number.isInteger(event.data.height) && event.data.height>=150 && event.data.height<=6000) setHeight(event.data.height);
    };
    window.addEventListener('message',resize);return()=>window.removeEventListener('message',resize);
  },[]);
  return <>
    {styles.map((href,index)=><link key={index} rel="stylesheet" href={href}/>)}
    <div className="activity-status"><span>{busy?'Rendering in Drupal…':`Last ${boot.days} ${boot.days===1?'day':'days'} · Native Drupal rendering · Demo snapshot ${boot.as_of || ''}`}</span><div><button onClick={()=>setMobile(!mobile)}>{mobile?'Full width':'Mobile'}</button> · <button onClick={()=>host.openLink(boot.website_url).catch(error=>setError(error.message))}>Open in Drupal ↗</button></div></div>
    <div className="activity-view" style={mobile?{maxWidth:390,margin:'0 auto'}:{}}>{html?<iframe ref={frame} title={boot.title || 'Editorial activity dashboard'} sandbox="allow-scripts" srcDoc={html} style={{height}}/>:<div className="activity-loading">Preparing your Drupal components…</div>}</div>
    <details className="activity-source"><summary>See the OpenUI composition · original Drupal SDCs</summary><textarea aria-label="OpenUI source" readOnly value={program}/></details>
  </>;
}
createRoot(document.getElementById('app')).render(<Shell/>);
