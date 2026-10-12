import {strict as assert} from 'node:assert';
import {readFile} from 'node:fs/promises';
import {JSDOM, VirtualConsole} from 'jsdom';
import {requestFor} from '../modules/mcp_apps_commerce_demo/ui/actions.js';

const fixture = JSON.parse(await readFile('/tmp/mcp-commerce-fixture.json', 'utf8'));
const choices = new JSDOM(fixture.choosing_preview.ui.html);
assert.equal(choices.window.document.querySelectorAll('.shop-specs[open]').length,2);
assert.equal(choices.window.document.querySelectorAll('[data-shop-compare]').length,0);
assert.equal(choices.window.document.querySelectorAll('.shop-comparison').length,0,'Do not duplicate the expanded product details in another table.');
choices.window.close();
// Exercise the actual shared component controls, including sandbox-safe buttons.
const components = new JSDOM(fixture.preview.ui.html, {runScripts:'outside-only'});
const events = [];
components.window.document.addEventListener('mcp-apps-commerce', event=>events.push(event.detail));
components.window.eval(await readFile(new URL('../modules/mcp_apps_commerce_demo/js/components.js',import.meta.url),'utf8'));
const product = components.window.document.querySelector('.shop-product');
const finish = product.querySelector('select');
finish.selectedIndex = 1;
finish.dispatchEvent(new components.window.Event('change',{bubbles:true}));
assert.equal(product.querySelector('[data-shop-price]').textContent,finish.selectedOptions[0].dataset.price);
assert.equal(product.querySelector('[data-shop-submit]').type,'button');
product.querySelector('[data-shop-submit]').click();
assert.deepEqual(JSON.parse(JSON.stringify(events.pop())),{action:'cart',args:{variation_id:Number(finish.value),quantity:1,add:true}});
components.window.document.querySelector('[data-shop-form="filter"] [name="budget"]').value='120';
components.window.document.querySelector('[data-shop-form="filter"] [data-shop-submit]').click();
assert.equal(events.pop().args.budget,120);
components.window.close();
const requests = [], errors = [];
let mode = 'opened', reject = false, active = 0, maximum = 0;
const logs = new VirtualConsole();
logs.on('jsdomError', error => errors.push(error.message));
const resultFor = value => ({structuredContent: {data:value.data}, _meta:{ui:value.ui}});
const dom = new JSDOM(await readFile(new URL('../modules/mcp_apps_commerce_demo/dist/shop.html', import.meta.url), 'utf8'), {
  url:'https://mcp-app.invalid/', runScripts:'dangerously', pretendToBeVisual:true, virtualConsole:logs,
  beforeParse(window) {
    Object.assign(window,{TextEncoder,TextDecoder,structuredClone,Blob});
    window.ResizeObserver = class {observe(){} disconnect(){}};
    const reply = data => queueMicrotask(() => window.dispatchEvent(new window.MessageEvent('message',{data,source:window.parent})));
    window.postMessage = request => {
      requests.push(request);
      if(request.method==='ui/initialize') reply({jsonrpc:'2.0',id:request.id,result:{protocolVersion:request.params.protocolVersion,hostInfo:{name:'Test host',version:'1'},hostCapabilities:{serverTools:{},updateModelContext:{text:{}}},hostContext:{theme:'light',displayMode:'inline'}}});
      else if(request.method==='ui/notifications/initialized') reply({jsonrpc:'2.0',method:'ui/notifications/tool-result',params:resultFor(fixture.opened)});
      else if(request.method==='tools/call') {
        active++; maximum=Math.max(maximum,active);
        const {name,arguments:args}=request.params;
        let result;
        if(name.endsWith('component_composer_asset')) {
          const bytes=Buffer.from(fixture.bytes[args.asset_id],'base64').subarray(args.offset,args.offset+196608);
          result={structuredContent:{data:{bytes:bytes.length}},_meta:{ui:{base64:bytes.toString('base64')}}};
        } else if(name.endsWith('component_composer_preview')) result=resultFor(fixture[mode==='opened'?'preview':mode+'_preview']);
        else if(reject) result={isError:true,content:[{type:'text',text:'Access denied for testing.'}]};
        else {
          if(name.endsWith('commerce_shop_filter')) mode=args.view==='basket'?'basket':args.view==='catalog'&&mode==='basket'?'cart':'compared';
          else if(name.endsWith('commerce_shop_cart')) mode='cart';
          else if(name.endsWith('commerce_shop_checkout')) mode='receipt';
          else throw new Error('Unexpected tool: '+name);
          result=resultFor(fixture[mode]);
        }
        setTimeout(()=>{active--; reply({jsonrpc:'2.0',id:request.id,result});},10);
      } else if(request.id!==undefined) reply({jsonrpc:'2.0',id:request.id,result:{}});
    };
  },
});
const waitFor=async(predicate,label)=>{
  for(let i=0;i<200;i++) {if(predicate())return;await new Promise(resolve=>setTimeout(resolve,25));}
  throw new Error('Timed out: '+label);
};
const action=(action,args={},source=dom.window.document.querySelector('iframe').contentWindow)=>dom.window.dispatchEvent(new dom.window.MessageEvent('message',{source,data:{type:'mcp-apps-commerce',action,args}}));
const html=()=>dom.window.document.querySelector('iframe')?.getAttribute('srcdoc')||'';
try {
  await waitFor(()=>html().includes('Studio hand grinder')&&active===0,'native preview');
  assert.equal(dom.window.document.querySelector('iframe').getAttribute('sandbox'),'allow-scripts');
  assert(html().includes('data:image/svg+xml;base64,'));
  assert(!html().includes('shop-intro'));
  assert(!html().includes('shop-basket-card'));
  const before=requests.length;
  action('checkout',{revision:'forged'},null);
  assert.equal(requests.length,before,'Ignore messages from other frames.');
  action('compare',{product_id:fixture.compared.ui.compare[0],selected:true});
  await waitFor(()=>html().includes('The details, side by side.')&&active===0,'comparison');
  const variation=fixture.cart.ui.cart.items[0].variation_id;
  action('cart',{variation_id:variation,quantity:1,add:true});
  await waitFor(()=>html().includes('Proceed')&&active===0,'compact basket summary');
  assert(!html().includes('shop-basket-card'));
  const update=requests.find(request=>request.method==='tools/call'&&request.params.name.endsWith('commerce_shop_cart'));
  assert.equal(update.params.arguments.quantity,1+(fixture.opened.ui.cart.items.find(item=>item.variation_id===variation)?.quantity||0));
  assert.equal(update.params.arguments.revision,fixture.opened.ui.cart.revision);
  action('view',{view:'basket'});
  await waitFor(()=>html().includes('reviewed-basket')&&active===0,'separate basket step');
  assert(!html().includes('data-shop-form="filter"'));
  assert(!html().includes('class="shop-product"'));
  action('view',{view:'catalog'});
  await waitFor(()=>html().includes('Proceed')&&active===0,'return to products');
  const back=requests.filter(request=>request.method==='tools/call'&&request.params.name.endsWith('commerce_shop_filter')).at(-1);
  assert.equal(back.params.arguments.category,'grinders');
  assert.equal(back.params.arguments.budget,150);
  action('view',{view:'basket'});
  await waitFor(()=>html().includes('reviewed-basket')&&active===0,'basket review');
  const previous=html();
  reject=true;
  action('checkout',{revision:'reviewed-basket'});
  await waitFor(()=>dom.window.document.querySelector('[role="alert"]')?.textContent.includes('Access denied'),'access error');
  assert.equal(html(),previous,'Keep the last valid view after a failed write.');
  reject=false;
  action('checkout',{revision:'reviewed-basket'});
  await waitFor(()=>html().includes('TEST ORDER COMPLETE')&&active===0,'receipt');
  const checkout=requests.filter(request=>request.method==='tools/call'&&request.params.name.endsWith('commerce_shop_checkout')).at(-1);
  assert.equal(checkout.params.arguments.order_id,99);
  assert.equal(checkout.params.arguments.revision,'reviewed-basket');
  assert(requests.some(request=>request.method==='ui/initialize'));
  assert(requests.some(request=>request.method==='ui/update-model-context'));
  assert.equal(maximum,1,'Serialize host tool calls.');
  assert.throws(()=>requestFor({...fixture.opened.ui,compare:[1,2,3]},'compare',{product_id:4,selected:true}),/three/);
  assert.throws(()=>requestFor(fixture.opened.ui,'arbitrary_tool'),/Unsupported/);
  assert.deepEqual(errors,[]);
  console.log('PASS: MCP SDK initialization, native SDC preview/assets, frame isolation, comparison, basket revision, checkout, error recovery and sequential tool calls.');
} finally {dom.window.close();}
