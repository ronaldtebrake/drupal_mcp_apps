import {requestFor} from './actions.js';
let boot=window.drupalSettings.mcpAppsCommerce.boot,busy=false;
const endpoint=window.drupalSettings.mcpAppsCommerce.endpoint,token=window.drupalSettings.mcpAppsCommerce.token;
document.addEventListener('mcp-apps-commerce',async event=>{
  if(busy) return;
  const {action,args}=event.detail;
  if(action==='link'){if(args.url===boot.receipt?.url)window.location.assign(args.url);return;}
  busy=true;
  const region=document.getElementById('mcp-apps-shop'),status=document.getElementById('mcp-apps-shop-status');
  region.setAttribute('aria-busy','true');
  try{
    const request=requestFor(boot,action,args);
    const response=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({name:request.name.replace(/^tool_api__/,''),arguments:request.arguments})});
    const result=await response.json();
    if(!response.ok||result.error)throw new Error(result.error||'Drupal refused the request.');
    boot=result.ui;region.innerHTML=result.html;status.textContent='';
  }catch(error){status.textContent=error.message;region.querySelectorAll('[data-shop-checkout]').forEach(button=>button.disabled=false);}
  finally{busy=false;region.setAttribute('aria-busy','false');}
});
