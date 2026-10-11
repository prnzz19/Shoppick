<script>
(() => {
    const root = document.getElementById('inventory-workspace');
    const filters = root.querySelector('#inventory-filters');
    const feedback = root.querySelector('#inventory-feedback');
    let timer, pending, saving = false;
    function syncFilters(url) {
        const params = new URL(url, location.href).searchParams;
        for (const field of filters.querySelectorAll('[name]')) field.value = params.get(field.name) || (field.name === 'sort' ? 'updated' : '');
    }
    async function load(url, push = true, sync = false) {
        pending?.abort();
        const controller = pending = new AbortController();
        root.querySelector('#inventory-results').setAttribute('aria-busy','true');
        try {
            const response = await fetch(url,{signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Could not load inventory. Please try again.');
            const doc = new DOMParser().parseFromString(await response.text(),'text/html');
            if (controller !== pending) return;
            if (!doc.querySelector('#inventory-results')) throw new Error('Your session may have expired. Refresh the page and sign in again.');
            for (const id of ['inventory-results','inventory-summary']) root.querySelector('#'+id).replaceWith(doc.querySelector('#'+id));
            const next = new URL(response.url);
            if (push) history.pushState({},'',next);
            else if (next.href !== location.href) history.replaceState({},'',next);
            if (sync) syncFilters(next);
        } catch (error) {
            if (error.name !== 'AbortError') { feedback.textContent = error.message; return false; }
        } finally {
            if (controller === pending) root.querySelector('#inventory-results').removeAttribute('aria-busy');
        }
        return true;
    }
    function filter() {
        if (saving) return;
        clearTimeout(timer);
        const url = new URL(filters.action);
        for (const [key,value] of new FormData(filters)) if (value.trim()) url.searchParams.set(key,value.trim());
        feedback.textContent = '';
        load(url);
    }
    filters.addEventListener('submit',event=>{event.preventDefault();filter();});
    filters.addEventListener('change',event=>{if(event.target.tagName==='SELECT') filter();});
    filters.querySelector('[name=q]').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(filter,400);});
    root.addEventListener('click',event=>{
        const close = event.target.closest('[data-close-editor]');
        if (close) { const item=close.closest('details');item.open=false;item.querySelector('summary').focus();return; }
        const link = event.target.closest('a[data-inventory-link], .inventory-pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button) return;
        event.preventDefault();if(saving)return;clearTimeout(timer);feedback.textContent='';load(link.href,true,true);
    });
    root.addEventListener('toggle',event=>{
        if(event.target.matches('details[open]')) root.querySelectorAll('details[open]').forEach(item=>{if(item!==event.target)item.open=false;});
    },true);
    window.addEventListener('popstate',()=>{clearTimeout(timer);syncFilters(location.href);load(location.href,false);});
    root.addEventListener('submit',async event=>{
        const form=event.target.closest('[data-stock-form]');if(!form)return;
        event.preventDefault();if(saving)return;saving=true;clearTimeout(timer);pending?.abort();
        form.querySelectorAll('[data-error],[data-form-error]').forEach(el=>el.textContent='');
        const payload=new FormData(form),button=form.querySelector('[type=submit]');button.disabled=true;
        filters.querySelectorAll('input,select').forEach(el=>el.disabled=true);
        try {
            const response=await fetch(form.action,{method:'POST',body:payload,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data=await response.json().catch(()=>({}));
            if(response.status===422){
                let first;
                for(const [key,messages] of Object.entries(data.errors || {})) {
                    const target=[...form.querySelectorAll('[data-error]')].find(el=>el.dataset.error===key) || form.querySelector('[data-form-error]');
                    target.textContent=messages.join(' ');first ||= target.closest('label')?.querySelector('input');
                }
                first?.focus();return;
            }
            if(!response.ok)throw new Error('Stock could not be updated. Refresh the page and try again.');
            feedback.textContent='Stock updated successfully.';
            const id=form.closest('details').dataset.product;
            const loaded=await load(location.href,false);
            if(loaded)root.querySelector('[data-product="'+id+'"] summary')?.focus({preventScroll:true});
            else feedback.textContent='Stock was saved, but the list could not refresh. Reload the page to see the latest quantities.';
        }catch(error){form.querySelector('[data-form-error]').textContent=error.message;}
        finally{saving=false;button.disabled=false;filters.querySelectorAll('input,select').forEach(el=>el.disabled=false);}
    });
})();
</script>
