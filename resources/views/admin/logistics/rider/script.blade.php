<script>
(() => {
 const root=document.querySelector('[data-rider-details]');if(!root)return;
 const form=root.querySelector('[data-rider-filters]'),error=root.querySelector('[data-rider-error]'),loading=root.querySelector('[data-rider-loading]'),retry=root.querySelector('[data-rider-retry]'),clear=root.querySelector('[data-rider-clear]');
 let timer,controller,retryQuery,generation=0;
 function busy(value){loading.hidden=!value;for(const name of ['shipments','activity']){const area=root.querySelector(`[data-rider-${name}]`);area.setAttribute('aria-busy',String(value));area.style.opacity=value?'.6':'';}root.dataset.state=value?'loading':'ready';}
 function controls(){clear.hidden=!form.elements.q.value.trim()&&!form.elements.status.value;const url=new URL(form.action);if(form.elements.activity_page.value!=='1')url.searchParams.set('activity_page',form.elements.activity_page.value);clear.href=url.href;}
 function params(){const p=new URLSearchParams();for(const [key,value] of new FormData(form)){const text=String(value).trim();if(text&&(key!=='activity_page'||text!=='1'))p.set(key,text);}return p;}
 function cancel(){clearTimeout(timer);generation++;controller?.abort();busy(false);}
 function restore(p){form.elements.q.value=p.get('q')??'';form.elements.status.value=p.get('status')??'';form.elements.activity_page.value=p.get('activity_page')??'1';controls();}
 async function load(p,mode='push'){
  cancel();controls();error.hidden=true;retry.hidden=true;const version=generation;controller=new AbortController();const pending=controller;let timedOut=false;const timeout=setTimeout(()=>{timedOut=true;pending.abort();},15000);retryQuery=new URLSearchParams(p);const url=new URL(form.action);url.search=p.toString();busy(true);
  try{const response=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store',signal:pending.signal});if(!response.ok||response.redirected||!response.headers.get('content-type')?.includes('application/json'))throw new Error('Request failed');const data=await response.json();if(version!==generation)return;const pageUrl=new URL(data.url,form.action);if(pageUrl.origin!==location.origin||pageUrl.pathname!==new URL(form.action).pathname||typeof data.shipments_html!=='string'||typeof data.activity_html!=='string')throw new Error('Invalid response');let focusArea;for(const name of ['shipments','activity']){const area=root.querySelector(`[data-rider-${name}]`);if(area.contains(document.activeElement))focusArea=area;area.innerHTML=data[name+'_html'];}form.elements.activity_page.value=pageUrl.searchParams.get('activity_page')??'1';if(focusArea)focusArea.focus({preventScroll:true});if(mode==='push'&&pageUrl.href!==location.href)history.pushState(null,'',pageUrl);else if(mode==='replace')history.replaceState(null,'',pageUrl);
  }catch(failure){if(version===generation&&(failure.name!=='AbortError'||timedOut)){error.textContent='Unable to update rider activity. Try again.';error.hidden=false;retry.hidden=false;}}
  finally{clearTimeout(timeout);if(version===generation){controls();busy(false);if(!error.hidden)root.dataset.state='error';}}
 }
 form.addEventListener('change',e=>{if(e.target.name!=='q')void load(params());});
 form.elements.q.addEventListener('input',()=>{cancel();controls();error.hidden=true;retry.hidden=true;timer=setTimeout(()=>{void load(params());},400);});
 form.addEventListener('submit',e=>{e.preventDefault();void load(params());});retry.addEventListener('click',()=>{void load(retryQuery??params());});
 root.addEventListener('click',e=>{if(e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;const link=e.target.closest('a');if(!link)return;if(link.matches('[data-rider-clear]')){e.preventDefault();const activity=form.elements.activity_page.value;restore(new URLSearchParams());form.elements.activity_page.value=activity;void load(params());}else if(link.closest('.rd-pagination')){const url=new URL(link.href);if(url.origin!==location.origin||url.pathname!==new URL(form.action).pathname)return;e.preventDefault();void load(url.searchParams);}});
 window.addEventListener('popstate',()=>{const p=new URLSearchParams(location.search);restore(p);void load(p,'replace');});controls();busy(false);
})();
</script>
