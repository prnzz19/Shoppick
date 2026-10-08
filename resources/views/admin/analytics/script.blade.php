<script>
(()=>{
const root=document.querySelector('[data-admin-analytics]');if(!root)return;
const form=root.querySelector('[data-analytics-form]'),content=root.querySelector('[data-analytics-content]'),loading=root.querySelector('[data-analytics-loading]'),error=root.querySelector('[data-analytics-error]');
let controller,sequence=0,lastUrl;
function custom(){const block=form.querySelector('[data-analytics-custom]');if(!block)return;const on=form.elements.range.value==='custom';block.hidden=!on;block.querySelectorAll('input').forEach(input=>{input.disabled=!on;input.required=on;});}
function url(){const target=new URL(form.action);new FormData(form).forEach((value,key)=>{if(value)target.searchParams.set(key,value);});return target;}
function busy(on){loading.hidden=!on;content.setAttribute('aria-busy',String(on));}
async function update(target,historyMode='push'){
 controller?.abort();controller=new AbortController();const active=controller,own=++sequence;lastUrl=target;error.hidden=true;busy(true);const timeout=setTimeout(()=>active.abort(),15000);
 try{const response=await fetch(target,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},signal:active.signal,credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.message||'Request failed');if(own!==sequence)return;content.innerHTML=data.content_html;if(historyMode!=='none')history[historyMode+'State']({},'',data.url);}
 catch(e){if(own===sequence)error.hidden=false;}
 finally{clearTimeout(timeout);if(own===sequence)busy(false);}
}
form.addEventListener('change',()=>{custom();if(!form.reportValidity())return;update(url());});
form.addEventListener('submit',event=>{event.preventDefault();custom();if(form.reportValidity())update(url());});
root.addEventListener('change',event=>{if(event.target.matches('[data-analytics-sort]')){form.elements.sort.value=event.target.value;update(url());}});
root.addEventListener('click',event=>{const page=event.target.closest('[data-analytics-pagination] a');if(page){event.preventDefault();update(new URL(page.href));}if(event.target.closest('[data-analytics-retry]'))update(lastUrl||url());});
window.addEventListener('popstate',()=>{const current=new URL(location.href);Array.from(form.elements).forEach(input=>{if(input.name)input.value=current.searchParams.get(input.name)||(input.name==='range'?'{{ $dashboard?'7days':'30days' }}':input.name==='sort'?'sales_desc':'');});custom();update(current,'none');});
custom();
})();
</script>
