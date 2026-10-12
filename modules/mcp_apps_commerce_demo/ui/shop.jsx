import React,{useEffect,useRef,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {Renderer} from '@openuidev/react-lang';
import {createHost,serializeCalls,toolProvider} from '../../../ui/host.js';
import {makeLibrary,parseComposition,toProgram} from '../../mcp_apps_openui/ui/library.js';
import {materializePreview} from '../../mcp_apps_openui/ui/assets.js';
import {names,requestFor} from './actions.js';
const browser=window.__DRUPAL_BROWSER_PREVIEW__;
let receive,cancelled=false,ticket=0,resources;
const host=browser?{
  call:serializeCalls(async(name,args)=>{
    if(!names.includes(name)) throw new Error('Unsupported shop operation.');
    const response=await fetch(browser.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':browser.token},body:JSON.stringify({name:name.replace(/^tool_api__/,''),arguments:args})});
    const result=await response.json();
    if(!response.ok||result.error) throw new Error(result.error||'Drupal refused the request.');
    return result;
  }),context:async()=>{},openLink:async url=>window.open(url,'_blank','noopener')
}:createHost({name:'Fieldwork Supply',receive:result=>receive?.(result),error:error=>window.dispatchEvent(new CustomEvent('shop-error',{detail:error.message})),cancel:()=>{cancelled=true;ticket++;window.dispatchEvent(new CustomEvent('shop-error',{detail:'The host cancelled this shop. Reopen it to continue.'}));}});
const provider=toolProvider(host,names),library=makeLibrary(Preview),Context=React.createContext(null);
function Shell(){
  const [boot,setBoot]=useState(null),[program,setProgram]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false);
  receive=result=>{
    const ui=result._meta?.ui;
    if(!ui?.session_id||!Array.isArray(ui.tree)){setError('The host did not forward shopping metadata. Refresh the connection and reopen the app.');return;}
    try{const source=toProgram(ui.tree,library);parseComposition(source,library);cancelled=false;setBoot(ui);setProgram(source);setError('');}catch(error){setError(error.message);}
  };
  useEffect(()=>{
    const report=event=>setError(event.detail);window.addEventListener('shop-error',report);
    if(browser) receive(browser.result);else host.connect();
    return()=>{window.removeEventListener('shop-error',report);resources?.dispose();};
  },[]);
  const act=async(action,args)=>{
    if(busy||cancelled) return;
    setBusy(true);
    try{const request=requestFor(boot,action,args);const result=await provider[request.name](request.arguments);if(!cancelled) receive(result);}
    catch(error){setError(error.message);await host.context({error:error.message}).catch(()=>{});}
    finally{setBusy(false);}
  };
  const refresh=()=>act('filter',boot.filters);
  return <Context.Provider value={{boot,program,setError,act,busy}}>
    {!boot?<div className="commerce-app-loading">{error||'Connecting to Drupal Commerce…'}</div>:<>
      <header className="commerce-app-toolbar"><span>Drupal Commerce · MCP App · Test store</span><div><button disabled={busy||cancelled} onClick={refresh}>Refresh</button> · <button onClick={()=>host.openLink(boot.website_url).catch(error=>setError(error.message))}>Open shop in Drupal ↗</button></div></header>
      {error&&<div className="commerce-app-error" role="alert">{error} Your last successful view remains visible.</div>}
      <Renderer library={library} response={program} toolProvider={provider} onError={error=>setError(error.message||String(error))}/>
    </>}
  </Context.Provider>;
}
function Preview({tree}){
  const {boot,program,setError,act,busy}=React.useContext(Context),[html,setHtml]=useState(''),[loading,setLoading]=useState(true),[height,setHeight]=useState(750),frame=useRef(null);
  const serial=JSON.stringify(tree);
  useEffect(()=>{
    const own=++ticket;setLoading(true);
    (async()=>{
      try{
        if(cancelled) return;
        const result=await provider.tool_api__component_composer_preview({session_id:boot.session_id,composition:serial});
        const next=await materializePreview(host,boot.session_id,result._meta.ui);
        if(own!==ticket||cancelled){next.dispose();return;}
        const old=resources;resources=next;setHtml(next.html);setTimeout(()=>old?.dispose(),2000);
        await host.context({message:boot.receipt?.order_id?'A simulated Commerce order was placed. No real charge.':'Drupal shopping view updated.',products:boot.tree[0].props.count,cart_count:boot.cart.count,cart_total:boot.cart.total,test_mode:true,order_id:boot.receipt?.order_id});
      }catch(error){if(own===ticket)setError(error.message);}finally{if(own===ticket)setLoading(false);}
    })();
  },[serial,boot]);
  useEffect(()=>{
    const handle=event=>{
      if(event.source!==frame.current?.contentWindow) return;
      if(event.data?.type==='mcp-apps-commerce-height'&&Number.isInteger(event.data.height)&&event.data.height>=150&&event.data.height<=6000)setHeight(event.data.height);
      if(event.data?.type==='mcp-apps-commerce'){
        if(event.data.action==='link'){if(event.data.args?.url===boot.receipt?.url)host.openLink(boot.receipt.url).catch(error=>setError(error.message));}
        else act(event.data.action,event.data.args);
      }
    };
    window.addEventListener('message',handle);return()=>window.removeEventListener('message',handle);
  },[boot,busy]);
  useEffect(()=>{frame.current?.contentWindow?.postMessage({type:'mcp-apps-commerce-busy',busy:busy||loading||cancelled},'*');},[busy,loading]);
  return <><div className="commerce-app-preview">{html?<iframe ref={frame} title="Drupal Commerce shopping components" sandbox="allow-scripts" srcDoc={html} style={{height}}/>:<div className="commerce-app-loading">Rendering your shop in Drupal…</div>}</div><details className="commerce-app-source"><summary>See the OpenUI composition · original Drupal components</summary><textarea readOnly aria-label="OpenUI source" value={program}/></details></>;
}
createRoot(document.getElementById('app')).render(<Shell/>);
