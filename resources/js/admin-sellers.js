// Enhance only the permission-protected Admin Sellers workspace.
export function setupAdminSellers(root = document.querySelector('[data-admin-sellers]')) {
    if (!root || root.dataset.enhanced === 'true') return;
    root.dataset.enhanced = 'true';
    const form = root.querySelector('[data-sellers-form]');
    const table = root.querySelector('[data-sellers-table]');
    const loading = root.querySelector('[data-sellers-loading]');
    const error = root.querySelector('[data-sellers-error]');
    const retry = root.querySelector('[data-sellers-retry]');
    const clear = root.querySelector('[data-sellers-clear-control]');
    const defaults = { q: '', shop: '', range: 'all', status: '', sort: 'newest', shop_status: '', sales: '', from: '', to: '', tab: '' };
    let debounce;
    let controller;
    let generation = 0;
    let retryParams;

    function setBusy(busy) {
        table.setAttribute('aria-busy', String(busy));
        table.classList.toggle('opacity-60', busy);
        loading.hidden = !busy;
        root.dataset.state = busy ? 'loading' : 'ready';
    }

    function cancelPending() {
        clearTimeout(debounce);
        generation++;
        controller?.abort();
        setBusy(false);
    }

    function showError(message, canRetry = false) {
        error.textContent = message;
        error.hidden = !message;
        retry.hidden = !canRetry;
        if (message) root.dataset.state = 'error';
    }

    function updateControls() {
        const custom = form.elements.range.value === 'custom';
        root.querySelector('#seller-custom-dates').hidden = !custom;
        for (const name of ['from', 'to']) {
            form.elements[name].disabled = !custom;
            form.elements[name].required = custom;
        }
        const params = formParams();
        clear.hidden = ![...params.keys()].some(key => key !== 'tab' && key !== 'page');
        const clearUrl = new URL(form.action);
        if (form.elements.tab.value === 'archived') clearUrl.searchParams.set('tab', 'archived');
        clear.href = clearUrl.href;
    }

    function formParams() {
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(form)) {
            if (['from', 'to'].includes(name) && form.elements.range.value !== 'custom') continue;
            const trimmed = String(value).trim();
            if (trimmed && trimmed !== defaults[name]) params.set(name, trimmed);
        }
        return params;
    }

    function restoreControls(params) {
        for (const [name, fallback] of Object.entries(defaults)) {
            form.elements[name].value = params.get(name) ?? fallback;
        }
        // Also honor older/shared links that use date_from/date_to.
        form.elements.from.value = params.get('from') ?? params.get('date_from') ?? '';
        form.elements.to.value = params.get('to') ?? params.get('date_to') ?? '';
        updateControls();
    }

    function validDates() {
        if (form.elements.range.value !== 'custom') return true;
        const from = form.elements.from;
        const to = form.elements.to;
        if (!from.value || !to.value) {
            showError('Choose both From and To dates to update sellers.');
            return false;
        }
        if (!from.validity.valid || !to.validity.valid || to.value < from.value) {
            showError('Choose a valid date range with To on or after From.');
            return false;
        }
        return true;
    }

    async function load(params, historyMode = 'push') {
        cancelPending();
        updateControls();
        showError('');
        if (!validDates()) return;
        const requestGeneration = generation;
        controller = new AbortController();
        const requestController = controller;
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; requestController.abort(); }, 15000);
        retryParams = new URLSearchParams(params);
        const url = new URL(root.dataset.filterUrl);
        url.search = params.toString();
        setBusy(true);
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', cache: 'no-store', signal: requestController.signal,
            });
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Request failed');
            const data = await response.json();
            if (requestGeneration !== generation) return;
            const sections = { summary: 'summary_html', table: 'table_html', pagination: 'pagination_html', tabs: 'tabs_html', period: 'period_html' };
            if (!Object.values(sections).every(key => typeof data[key] === 'string')) throw new Error('Invalid response');
            const pageUrl = new URL(data.url, form.action);
            if (pageUrl.origin !== location.origin || pageUrl.pathname !== new URL(form.action).pathname) throw new Error('Invalid URL');
            const resultHadFocus = ['table', 'pagination', 'tabs'].some(section => root.querySelector(`[data-sellers-${section}]`).contains(document.activeElement));
            for (const [section, key] of Object.entries(sections)) root.querySelector(`[data-sellers-${section}]`).innerHTML = data[key];
            // Keep controls in place so focus, typing and layout scroll are preserved.
            updateControls();
            if (resultHadFocus) table.focus({ preventScroll: true });
            if (historyMode === 'push' && pageUrl.href !== location.href) history.pushState(null, '', pageUrl);
            else if (historyMode === 'replace') history.replaceState(null, '', pageUrl);
            root.dispatchEvent(new CustomEvent('sellers:updated', { detail: { total: data.total, page: data.page } }));
        } catch (failure) {
            if (requestGeneration !== generation) return;
            if (failure.name !== 'AbortError' || timedOut) showError('Unable to update sellers. Try again.', true);
        } finally {
            clearTimeout(timeout);
            if (requestGeneration === generation) {
                setBusy(false);
                if (!error.hidden) root.dataset.state = 'error';
            }
        }
    }

    form.addEventListener('change', event => {
        if (event.target.name === 'q') return; // Search uses the input debounce.
        updateControls();
        void load(formParams());
    });
    form.elements.q.addEventListener('input', () => {
        cancelPending(); // Invalidate old responses as soon as the next query is typed.
        updateControls();
        showError('');
        debounce = setTimeout(() => { void load(formParams()); }, 400);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        void load(formParams());
    });
    retry.addEventListener('click', () => { void load(retryParams ?? formParams()); });
    root.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a');
        if (!link || !root.contains(link)) return;
        if (link.matches('[data-sellers-clear]')) {
            event.preventDefault();
            const tab = form.elements.tab.value;
            restoreControls(new URLSearchParams(tab === 'archived' ? 'tab=archived' : ''));
            void load(formParams());
        } else if (link.closest('[data-sellers-pagination], [data-sellers-tabs]')) {
            const target = new URL(link.href);
            if (target.origin !== location.origin || target.pathname !== new URL(form.action).pathname) return;
            event.preventDefault();
            if (link.closest('[data-sellers-tabs]')) form.elements.tab.value = target.searchParams.get('tab') ?? '';
            const params = formParams();
            if (link.closest('[data-sellers-pagination]') && target.searchParams.has('page')) params.set('page', target.searchParams.get('page'));
            void load(params);
        }
    });
    window.addEventListener('popstate', () => {
        restoreControls(new URLSearchParams(location.search));
        const params = formParams();
        const page = new URLSearchParams(location.search).get('page');
        if (page) params.set('page', page);
        void load(params, 'replace');
    });

    // Delegated handler works for archive/restore forms returned in later partials.
    root.addEventListener('submit', event => {
        const archiveForm = event.target.closest('form[data-entity-archive-form]');
        if (!archiveForm || archiveForm.dataset.confirmed !== 'true') return;
        if (archiveForm.dataset.submitting === 'true') { event.preventDefault(); return; }
        archiveForm.dataset.submitting = 'true';
        archiveForm.querySelector('button[type="submit"]').disabled = true;
        const confirm = document.querySelector('[data-confirm-submit]');
        if (confirm) confirm.disabled = true;
    });

    updateControls();
    setBusy(false);
}

document.addEventListener('DOMContentLoaded', () => setupAdminSellers());
