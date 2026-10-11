import {setupSalesCharts, destroySalesCharts} from './admin-sales-charts';
document.addEventListener('DOMContentLoaded',()=>{
const root=document.querySelector('[data-admin-analytics]');if(!root)return;
const form=root.querySelector('[data-analytics-form]'),content=root.querySelector('[data-analytics-content]'),loading=root.querySelector('[data-analytics-loading]'),error=root.querySelector('[data-analytics-error]');
let controller,sequence=0,lastUrl;
function custom(){const block=form.querySelector('[data-analytics-custom]');if(!block)return;const on=form.elements.range.value==='custom';block.hidden=!on;block.querySelectorAll('input').forEach(input=>{input.disabled=!on;input.required=on;});}
// Keep local/Cloudflare requests on the browser origin, including legacy absolute pagination URLs.
function localUrl(value){const parsed=new URL(value,location.href);return new URL(parsed.pathname+parsed.search,location.origin);}
function url(){const target=localUrl(form.action);new FormData(form).forEach((value,key)=>{if(value)target.searchParams.set(key,value);});return target;}
function busy(on){loading.hidden=!on;content.setAttribute('aria-busy',String(on));}
async function update(target,historyMode='push'){
 target=localUrl(target);
 controller?.abort();controller=new AbortController();const active=controller,own=++sequence;lastUrl=target;error.hidden=true;busy(true);const timeout=setTimeout(()=>active.abort(),15000);
 try{const response=await fetch(target,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},signal:active.signal,credentials:'same-origin'});if(!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json'))throw new Error('Analytics request failed');
 const data=await response.json();
 if(typeof data.content_html!=='string' || !data.content_html.trim() || typeof data.url!=='string')throw new Error('Invalid analytics response');
 const nextUrl=localUrl(data.url);
 const fragment=document.createElement('template');fragment.innerHTML=data.content_html;
 if(root.hasAttribute('data-sales-overview-host') && !fragment.content.querySelector('[data-dashboard-overview]'))throw new Error('Invalid dashboard response');
 fragment.content.querySelectorAll('[data-sales-chart-data]').forEach(node=>JSON.parse(node.textContent));
 if(own!==sequence)return;
 destroySalesCharts(content);content.replaceChildren(fragment.content);await setupSalesCharts(content);
 if(own!==sequence)return;
 if(historyMode!=='none')history[historyMode+'State']({},'',nextUrl);}
 catch(e){if(own===sequence)error.hidden=false;}
 finally{clearTimeout(timeout);if(own===sequence)busy(false);}
}
form.addEventListener('change',()=>{custom();if(!form.reportValidity())return;update(url());});
form.addEventListener('submit',event=>{event.preventDefault();custom();if(form.reportValidity())update(url());});
root.addEventListener('change',event=>{if(event.target.matches('[data-analytics-sort]')){form.elements.sort.value=event.target.value;update(url());}});
root.addEventListener('click',event=>{const page=event.target.closest('[data-analytics-pagination] a');if(page){event.preventDefault();update(new URL(page.href));}if(event.target.closest('[data-analytics-retry]'))update(lastUrl||url());});
window.addEventListener('popstate',()=>{const current=new URL(location.href);Array.from(form.elements).forEach(input=>{if(input.name)input.value=current.searchParams.get(input.name)||(input.name==='range'?(root.dataset.defaultRange || '30days'):input.name==='sort'?'sales_desc':'');});custom();update(current,'none');});
custom();
});
