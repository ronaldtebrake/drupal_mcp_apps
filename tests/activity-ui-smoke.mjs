import {strict as assert} from 'node:assert';
import {readFile} from 'node:fs/promises';
import {JSDOM, VirtualConsole} from 'jsdom';
import {makeLibrary, parseComposition, toProgram} from '../modules/mcp_apps_openui/ui/library.js';
const fixture=JSON.parse(await readFile(process.env.MCP_APPS_ACTIVITY_FIXTURE || '/tmp/mcp-activity-fixture.json','utf8'));
const view=fixture.opened._meta.ui.view;
const library=makeLibrary(()=>null);
const program=toProgram(fixture.opened._meta.ui.tree,library);
assert.equal(toProgram(parseComposition(program,library),library),program);
const requests=[],errors=[],logs=new VirtualConsole();
let rejectFilter=false,rejectPreview=false,filtered=false,customFiltered=false,themeSwitched=false,active=0,maximum=0;
logs.on('jsdomError',error=>errors.push(error.message));
const waitFor=async(predicate,label)=>{
  for(let i=0;i<200;i++){if(predicate()) return;await new Promise(resolve=>setTimeout(resolve,25));}
  throw new Error('Timed out: '+label);
};
const dom=new JSDOM(await readFile(new URL('../modules/mcp_apps_activity_demo/dist/activity.html',import.meta.url),'utf8'),{
  url:'https://mcp-app.invalid/',runScripts:'dangerously',pretendToBeVisual:true,virtualConsole:logs,
  beforeParse(window){
    window.TextEncoder=TextEncoder;window.TextDecoder=TextDecoder;window.structuredClone=structuredClone;window.Blob=Blob;
    window.ResizeObserver=class{observe(){}disconnect(){}};
    const reply=value=>queueMicrotask(()=>window.dispatchEvent(new window.MessageEvent('message',{data:value,source:window.parent})));
    window.postMessage=request=>{
      requests.push(request);
      if(request.method==='ui/initialize') reply({jsonrpc:'2.0',id:request.id,result:{protocolVersion:request.params.protocolVersion,hostInfo:{name:'Test host',version:'1'},hostCapabilities:{serverTools:{},updateModelContext:{text:{}}},hostContext:{theme:'light',displayMode:'inline'}}});
      else if(request.method==='ui/notifications/initialized') reply({jsonrpc:'2.0',method:'ui/notifications/tool-result',params:fixture.opened});
      else if(request.method==='tools/call'){
        active++;maximum=Math.max(maximum,active);
        const {name,arguments:args}=request.params;let result;
        if(name.endsWith('activity_dashboard_filter')){
          if(rejectFilter) result={isError:true,content:[{type:'text',text:'Access denied for testing.'}]};
          else{filtered=true;customFiltered=args.days===13;result=customFiltered?fixture.custom:themeSwitched?fixture.switched:fixture.filtered;}
        }else if(name.endsWith('component_composer_preview')){
          const preview=customFiltered?fixture.custom_preview:themeSwitched?fixture.switched_preview:filtered?fixture.filtered_preview:fixture.preview;
          result=rejectPreview?{isError:true,content:[{type:'text',text:'Rejected preview for testing.'}]}:{structuredContent:{data:preview.data},_meta:{ui:preview.ui}};
        }else if(name.endsWith('component_composer_asset')){
          const bytes=Buffer.from(fixture.bytes[args.asset_id],'base64').subarray(args.offset,args.offset+196608);
          result={structuredContent:{data:{bytes:bytes.length}},_meta:{ui:{base64:bytes.toString('base64')}}};
        }else throw new Error('Unexpected tool: '+name);
        setTimeout(()=>{active--;reply({jsonrpc:'2.0',id:request.id,result});},10);
      }else if(request.id!==undefined) reply({jsonrpc:'2.0',id:request.id,result:{}});
    };
  }
});
try{
  const doc=dom.window.document;
  await waitFor(()=>doc.querySelector('iframe')?.getAttribute('srcdoc'),'SDK handshake and native preview');
  const frame=doc.querySelector('iframe');
  assert.equal(frame.getAttribute('sandbox'),'allow-scripts');
  assert(frame.getAttribute('srcdoc').includes('data:text/javascript;base64,'));
  assert(frame.getAttribute('srcdoc').includes(view==='readership'?'Readership over time':'A weekend of discovery in Rotterdam'));
  assert.equal(doc.querySelector('.activity-toolbar strong').textContent,fixture.opened._meta.ui.title);
  assert(doc.querySelector('.activity-toolbar small').textContent.includes(fixture.opened._meta.ui.theme_label));
  assert([...doc.querySelectorAll('link[rel="stylesheet"]')].every(link=>link.href.startsWith('data:text/css;base64,')));
  if(view!=='dashboard') assert(!frame.getAttribute('srcdoc').includes('pulse-dashboard'));
  const initialHtml=frame.getAttribute('srcdoc');
  doc.querySelector('[aria-label="Section"]').value='culture';
  doc.querySelector('[aria-label="Section"]').dispatchEvent(new dom.window.Event('change',{bubbles:true}));
  await waitFor(()=>doc.querySelector('[aria-label="Period"]').value==='7' && frame.getAttribute('srcdoc')!==initialHtml,'host-mediated filter and rerender');
  const filter=requests.find(request=>request.method==='tools/call' && request.params.name.endsWith('activity_dashboard_filter'));
  assert.equal(filter.params.arguments.section,'culture');
  assert.equal(filter.params.arguments.days,30);
  assert.equal(doc.querySelector('[aria-label="Section"]').value,'culture');
  assert.equal(fixture.filtered._meta.ui.view,view);
  assert.equal(doc.querySelector('.activity-toolbar strong').textContent,fixture.opened._meta.ui.title);
  assert.equal(frame.getAttribute('title'),fixture.opened._meta.ui.title);
  assert(!frame.getAttribute('srcdoc').includes('Five places for your first coffee'));
  if(fixture.switched){
    const oldHtml=frame.getAttribute('srcdoc');
    themeSwitched=true;
    doc.querySelector('[aria-label="Refresh dashboard"]').click();
    await waitFor(()=>frame.getAttribute('srcdoc')!==oldHtml,'theme-only refresh with the same component tree');
    assert.deepEqual(fixture.switched._meta.ui.tree,fixture.filtered._meta.ui.tree);
    assert(doc.querySelector('.activity-toolbar small').textContent.includes(fixture.switched._meta.ui.theme_label));
    assert.equal(doc.querySelector('[aria-label="Section"]').value,'culture');
    assert.equal(doc.querySelector('[aria-label="Period"]').value,'7');
  }
  const countFilters=()=>requests.filter(request=>request.method==='tools/call' && request.params.name.endsWith('activity_dashboard_filter')).length;
  const beforeCustom=countFilters();
  const beforeCustomHtml=frame.getAttribute('srcdoc');
  doc.querySelector('[aria-label="Period"]').value='custom';
  doc.querySelector('[aria-label="Period"]').dispatchEvent(new dom.window.Event('change',{bubbles:true}));
  await waitFor(()=>doc.querySelector('[aria-label="Number of days"]'),'custom period controls');
  const daysInput=doc.querySelector('[aria-label="Number of days"]');
  const setDays=value=>{
    Object.getOwnPropertyDescriptor(dom.window.HTMLInputElement.prototype,'value').set.call(daysInput,value);
    daysInput.dispatchEvent(new dom.window.Event('input',{bubbles:true}));
    daysInput.dispatchEvent(new dom.window.Event('change',{bubbles:true}));
  };
  assert.equal(daysInput.min,'1');assert.equal(daysInput.max,'30');assert.equal(daysInput.step,'1');
  for(const invalid of ['31','0','14.5','']){
    setDays(invalid);
    assert(!daysInput.checkValidity());
    doc.querySelector('.activity-period button[type="submit"]').click();
    assert.equal(countFilters(),beforeCustom,'Invalid periods must not call the server.');
  }
  setDays('13');
  assert.equal(countFilters(),beforeCustom,'Editing a period must not call the server.');
  doc.querySelector('.activity-period button[type="submit"]').click();
  await waitFor(()=>doc.querySelector('[aria-label="Number of days"]')?.value==='13' && countFilters()===beforeCustom+1 && frame.getAttribute('srcdoc')!==beforeCustomHtml,'custom period apply and rerender');
  const customRequest=requests.filter(request=>request.method==='tools/call' && request.params.name.endsWith('activity_dashboard_filter')).at(-1);
  assert.equal(customRequest.params.arguments.days,13);assert.equal(customRequest.params.arguments.section,'culture');
  assert.equal(fixture.custom._meta.ui.view,view);
  [...doc.querySelectorAll('button')].find(button=>button.textContent==='Mobile').click();
  await waitFor(()=>doc.querySelector('.activity-view').style.maxWidth==='390px','responsive width');
  const previous=frame.getAttribute('srcdoc');
  rejectFilter=true;
  doc.querySelector('[aria-label="Refresh dashboard"]').click();
  await waitFor(()=>doc.querySelector('[role="alert"]')?.textContent.includes('Access denied'),'filter error');
  assert.equal(frame.getAttribute('srcdoc'),previous);
  assert.equal(maximum,1,'Calls must remain sequential within the MCP session.');
  assert(requests.some(request=>request.method==='ui/update-model-context'));
  const iframeWindow=frame.contentWindow;
  dom.window.dispatchEvent(new dom.window.MessageEvent('message',{data:{type:'mcp-apps-activity-height',height:800},source:dom.window.parent}));
  assert.notEqual(frame.style.height,'800px','Ignore resize messages from other windows.');
  dom.window.dispatchEvent(new dom.window.MessageEvent('message',{data:{type:'mcp-apps-activity-height',height:800},source:iframeWindow}));
  await waitFor(()=>frame.style.height==='800px','bounded iframe resizing');
  dom.window.dispatchEvent(new dom.window.MessageEvent('message',{data:{type:'mcp-apps-activity-height',height:100000},source:iframeWindow}));
  assert.equal(frame.style.height,'800px','Ignore oversized resize messages.');
  dom.window.dispatchEvent(new dom.window.MessageEvent('message',{data:{jsonrpc:'2.0',method:'ui/notifications/tool-cancelled',params:{}},source:dom.window.parent}));
  await waitFor(()=>doc.querySelector('[aria-label="Refresh dashboard"]').disabled,'host cancellation');
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS: official OpenUI and MCP Apps SDK, native chart assets, host tool filtering, last-valid preview, serialized calls, widths, bounded frame resizing and cancellation.');
}finally{dom.window.close();}
