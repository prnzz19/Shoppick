// Uses the Sellers workspace's fetch/abort/history pattern for read-only reports.
export function setupAdminSalesReports(root = document.querySelector('[data-admin-sales-reports]')) {
    if (!root || root.dataset.enhanced === 'true') return;
    root.dataset.enhanced = 'true';
    const form = root.querySelector('[data-sales-form]');
    const table = root.querySelector('[data-sales-table]');
    const loading = root.querySelector('[data-sales-loading]');
    const error = root.querySelector('[data-sales-error]');
    const retry = root.querySelector('[data-sales-retry]');
    const clear = root.querySelector('[data-sales-clear-control]');
    const defaults = { shop: '', range: 'all', shop_status: '', performance: '', sort: 'sales_desc', q: '', from: '', to: '' };
    const sections = ['summary','table','pagination','period','exports'];
    let timer, controller, retryParams;
    let generation = 0;

    function busy(value) {
        table.setAttribute('aria-busy',String(value));
        table.classList.toggle('opacity-60',value);
        loading.hidden = !value;
        // Export links must represent the displayed results, never pending filters.
        const exports = root.querySelector('[data-sales-exports]');
        exports.inert = value;
        exports.classList.toggle('opacity-60',value);
        root.dataset.state = value ? 'loading' : 'ready';
    }
    function cancel() {
        clearTimeout(timer);
        generation++;
        controller?.abort();
        busy(false);
    }
    function fail(message, canRetry = false) {
        error.textContent = message;
        error.hidden = !message;
        retry.hidden = !canRetry;
        if (message) root.dataset.state = 'error';
    }
    function params() {
        const query = new URLSearchParams();
        for (const [name,value] of new FormData(form)) {
            const text = String(value).trim();
            if (text && text !== defaults[name]) query.set(name,text);
        }
        return query;
    }
    function controls() {
        const custom = form.elements.range.value === 'custom';
        root.querySelector('[data-sales-custom]').hidden = !custom;
        for (const key of ['from','to']) {
            form.elements[key].disabled = !custom;
            form.elements[key].required = custom;
        }
        clear.hidden = params().size === 0;
    }
    function restore(query) {
        for (const [name,fallback] of Object.entries(defaults)) form.elements[name].value = query.get(name) ?? fallback;
        controls();
    }
    async function load(query, historyMode = 'push') {
        cancel();
        controls();
        fail('');
        if (form.elements.range.value === 'custom') {
            const from = form.elements.from, to = form.elements.to;
            if (!from.value || !to.value) { fail('Choose both From and To dates to update reports.'); return; }
            if (!from.validity.valid || !to.validity.valid || to.value < from.value) { fail('Choose a valid date range with To on or after From.'); return; }
        }
        const current = generation;
        controller = new AbortController();
        const pending = controller;
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; pending.abort(); },15000);
        retryParams = new URLSearchParams(query);
        const url = new URL(root.dataset.filterUrl);
        url.search = query.toString();
        busy(true);
        try {
            const response = await fetch(url,{ headers: { Accept: 'application/json','X-Requested-With':'XMLHttpRequest' },credentials:'same-origin',cache:'no-store',signal:pending.signal });
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Request failed');
            const data = await response.json();
            if (current !== generation) return;
            if (!sections.every(part => typeof data[`${part}_html`] === 'string')) throw new Error('Invalid report response');
            const pageUrl = new URL(data.url,form.action);
            if (pageUrl.origin !== location.origin || pageUrl.pathname !== new URL(form.action).pathname) throw new Error('Invalid URL');
            const hadFocus = ['table','pagination','exports'].some(part => root.querySelector(`[data-sales-${part}]`).contains(document.activeElement));
            for (const part of sections) root.querySelector(`[data-sales-${part}]`).innerHTML = data[`${part}_html`];
            controls();
            if (hadFocus) table.focus({preventScroll:true});
            if (historyMode === 'push' && pageUrl.href !== location.href) history.pushState(null,'',pageUrl);
            else if (historyMode === 'replace') history.replaceState(null,'',pageUrl);
            root.dispatchEvent(new CustomEvent('sales-reports:updated',{detail:{total:data.total,page:data.page}}));
        } catch (failure) {
            if (current === generation && (failure.name !== 'AbortError' || timedOut)) fail('Unable to update sales reports. Try again.',true);
        } finally {
            clearTimeout(timeout);
            if (current === generation) { busy(false); if (!error.hidden) root.dataset.state = 'error'; }
        }
    }
    form.addEventListener('change',event => {
        if (event.target.name === 'q') return;
        controls();
        void load(params());
    });
    form.elements.q.addEventListener('input',() => {
        cancel(); controls(); fail('');
        timer = setTimeout(() => { void load(params()); },400);
    });
    form.addEventListener('submit',event => { event.preventDefault(); void load(params()); });
    retry.addEventListener('click',() => { void load(retryParams ?? params()); });
    root.addEventListener('click',event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a');
        if (!link || !root.contains(link)) return;
        if (link.matches('[data-sales-clear]')) {
            event.preventDefault(); restore(new URLSearchParams()); void load(params());
        } else if (link.closest('[data-sales-pagination]')) {
            const target = new URL(link.href);
            if (target.origin !== location.origin || target.pathname !== new URL(form.action).pathname) return;
            event.preventDefault();
            const query = params();
            if (target.searchParams.has('page')) query.set('page',target.searchParams.get('page'));
            void load(query);
        }
    });
    window.addEventListener('popstate',() => {
        const query = new URLSearchParams(location.search);
        restore(query);
        const target = params();
        if (query.has('page')) target.set('page',query.get('page'));
        void load(target,'replace');
    });
    controls(); busy(false);
}
document.addEventListener('DOMContentLoaded',() => setupAdminSalesReports());
