import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    setupGlobalState();
    setupSearchAutocomplete();
    setupFlashToast();
    setupConfirmModal();
});

function setupConfirmModal() {
    const modal = document.getElementById('shoppick-confirm');
    if (!modal) return;
    const panel = modal.querySelector('[data-confirm-panel]');
    const title = modal.querySelector('#shoppick-confirm-title');
    const message = modal.querySelector('#shoppick-confirm-message');
    const icon = modal.querySelector('[data-confirm-icon]');
    const cancel = modal.querySelector('[data-confirm-cancel]');
    const submit = modal.querySelector('[data-confirm-submit]');
    const reasonWrap = modal.querySelector('[data-confirm-reason-wrap]');
    const reason = modal.querySelector('#shoppick-confirm-reason');
    const reasonError = modal.querySelector('[data-confirm-reason-error]');
    let targetForm = null;
    let trigger = null;

    const styles = {
        success: ['bg-brand-600 hover:bg-brand-700', 'border-brand-400 bg-brand-50 text-brand-600', '✓'],
        warning: ['bg-accent-500 hover:bg-accent-600', 'border-accent-400 bg-accent-50 text-accent-500', '!'],
        danger: ['bg-rose-600 hover:bg-rose-700', 'border-rose-400 bg-rose-50 text-rose-600', '!'],
    };

    function closeModal() {
        panel.classList.add('scale-95', 'opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 180);
        document.body.style.overflow = '';
        targetForm = null;
        trigger?.focus();
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-title]');
        if (!form || form.dataset.confirmed === 'true') return;
        event.preventDefault();
        targetForm = form;
        trigger = event.submitter || document.activeElement;
        const type = form.dataset.confirmType || 'warning';
        const style = styles[type] || styles.warning;
        title.textContent = form.dataset.confirmTitle;
        message.textContent = form.dataset.confirmMessage || '';
        submit.textContent = form.dataset.confirmAction || 'Confirm';
        submit.className = `min-w-32 rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-sm transition ${style[0]}`;
        icon.className = `mx-auto flex h-20 w-20 items-center justify-center rounded-full border-2 text-3xl font-black ${style[1]}`;
        icon.textContent = style[2];
        const needsReason = form.dataset.confirmReason === 'true';
        reasonWrap.classList.toggle('hidden', !needsReason);
        reason.value = '';
        reasonError.classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
            (needsReason ? reason : cancel).focus();
        });
    });

    submit.addEventListener('click', () => {
        if (!targetForm) return;
        if (targetForm.dataset.confirmReason === 'true' && !reason.value.trim()) {
            reasonError.classList.remove('hidden');
            reason.focus();
            return;
        }
        if (targetForm.dataset.confirmReason === 'true') {
            let input = targetForm.querySelector('input[name="reason"]');
            if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'reason'; targetForm.appendChild(input); }
            input.value = reason.value.trim();
        }
        targetForm.dataset.confirmed = 'true';
        targetForm.requestSubmit();
    });
    cancel.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal && targetForm?.dataset.confirmReason !== 'true') closeModal(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
}

function setupGlobalState() {
    window.showToast = function (message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const colors = {
            success: 'bg-leaf-500',
            error: 'bg-rose-600',
            info: 'bg-brand-600',
        };
        const el = document.createElement('div');
        el.className = `rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg ${colors[type] || colors.info} animate-[fadeInUp_.25s_ease]`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3000);
    };
}

function setupFlashToast() {
    const error = document.querySelector('[data-flash-error]');
    const success = document.querySelector('[data-flash-success]');
    if (error && error.textContent.trim()) window.showToast(error.textContent.trim(), 'error');
    if (success && success.textContent.trim()) window.showToast(success.textContent.trim(), 'success');
}

async function api(url, options = {}) {
    const defaults = {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
            ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
        ...options,
    };
    if (options.body && !(options.body instanceof FormData)) {
        defaults.body = JSON.stringify(options.body);
    }
    const res = await fetch(url, defaults);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw (data.message || 'Request failed');
    return data;
}

window.toggleWishlist = async function (event) {
    event.preventDefault();
    event.stopPropagation();
    const button = event.currentTarget;
    const productId = Number.parseInt(button?.dataset.productId || '', 10);
    if (!Number.isInteger(productId) || productId < 1) {
        window.showToast('Unable to identify this product. Please refresh and try again.', 'error');
        return;
    }
    if (button?.disabled) return;
    if (button) button.disabled = true;
    try {
        const data = await api('/wishlist/toggle', { method: 'POST', body: { product_id: productId } });
        updateWishlistButtons(productId, data.added);
        refreshWishlistBadge(data.count);
        window.showToast(data.added ? 'Added to your wishlist.' : 'Removed from your wishlist.', data.added ? 'success' : 'info');
    } catch (e) {
        const message = typeof e === 'string' ? e : (e.message || 'Unable to update your wishlist.');
        if (message === 'Unauthenticated.' || message.includes('Login')) {
            window.location.href = '/login';
            return;
        }
        window.showToast(message, 'error');
    } finally {
        if (button) button.disabled = false;
    }
};

function updateWishlistButtons(productId, added) {
    document.querySelectorAll(`[data-wishlist][data-product-id="${productId}"]`).forEach((button) => {
        button.classList.toggle('text-rose-500', added);
        button.classList.toggle('text-navy-700', !added);
        button.setAttribute('aria-pressed', added ? 'true' : 'false');
        button.setAttribute('aria-label', added ? 'Remove from wishlist' : 'Add to wishlist');
        button.title = added ? 'Remove from wishlist' : 'Add to wishlist';
        const icon = button.querySelector('[data-wishlist-icon]');
        if (icon) icon.setAttribute('fill', added ? 'currentColor' : 'none');
    });
}

function refreshWishlistBadge(count) {
    document.querySelectorAll('[data-wishlist-count]').forEach((el) => {
        el.textContent = count;
        el.classList.toggle('hidden', !(count > 0));
    });
}

window.quickAdd = async function (event) {
    event.preventDefault();
    event.stopPropagation();
    const button = event.currentTarget;
    const productId = Number.parseInt(button?.dataset.productId || '', 10);
    if (!Number.isInteger(productId) || productId < 1) {
        window.showToast('Unable to identify this product. Please refresh and try again.', 'error');
        return;
    }
    if (button) button.disabled = true;
    try {
        const form = new FormData();
        form.append('product_id', productId);
        form.append('quantity', 1);
        const data = await api('/cart/add', { method: 'POST', body: form });
        window.showToast('Product added to cart.', 'success');
        refreshCartBadge(data.cart_count);
    } catch (e) {
        const message = typeof e === 'string' ? e : (e.message || 'Unable to add this product.');
        if (message === 'Unauthenticated.') { window.location.href = '/login'; return; }
        window.showToast(message, 'error');
    } finally {
        if (button) button.disabled = false;
    }
};

function refreshCartBadge(count) {
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        el.textContent = count;
        el.classList.toggle('hidden', !(count > 0));
    });
}

function setupSearchAutocomplete() {
    const input = document.getElementById('search-input');
    const box = document.getElementById('search-suggestions');
    if (!input || !box || !window.APP_ROUTES?.autocomplete) return;

    let timer;
    const MIN = 1;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < MIN) { box.classList.add('hidden'); box.innerHTML = ''; return; }
        timer = setTimeout(async () => {
            try {
                const url = new URL(window.APP_ROUTES.autocomplete, window.location.origin);
                url.searchParams.set('q', q);
                const res = await fetch(url);
                const data = await res.json();
                renderSuggestions(data, q);
            } catch (e) { box.classList.add('hidden'); }
        }, 250);
    });

    input.addEventListener('blur', () => setTimeout(() => box.classList.add('hidden'), 150));
    input.addEventListener('focus', () => { if (input.value.trim()) input.dispatchEvent(new Event('input')); });

    document.addEventListener('click', () => box.classList.add('hidden'));

    function renderSuggestions(data, q) {
        box.innerHTML = '';
        const header = document.createElement('p');
        header.className = 'px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400';
        header.textContent = 'Products';
        box.appendChild(header);

        if (data.products && data.products.length) {
            data.products.forEach((p) => {
                const a = document.createElement('a');
                a.href = `/product/${p.slug}`;
                a.className = 'flex items-center gap-3 px-3 py-2 hover:bg-slate-50';
                a.innerHTML = `
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">${p.image ? `<img src="/storage/${p.image}" class="h-full w-full object-cover">` : ''}</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-navy-800">${escapeHtml(p.name)}</p>
                        <p class="text-xs font-semibold text-accent-600">₱${Number(p.price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    </div>`;
                box.appendChild(a);
            });
        } else {
            box.appendChild(emptyState('No products found'));
        }

        if (data.categories && data.categories.length) {
            const cHeader = document.createElement('p');
            cHeader.className = 'border-t border-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400';
            cHeader.textContent = 'Categories';
            box.appendChild(cHeader);
            data.categories.forEach((c) => {
                const a = document.createElement('a');
                a.href = `/category/${c.id}`;
                a.className = 'block px-3 py-2 text-sm text-navy-700 hover:bg-slate-50';
                a.textContent = escapeHtml(c.name);
                box.appendChild(a);
            });
        }

        box.classList.remove('hidden');
    }

    function emptyState(msg) {
        const div = document.createElement('div');
        div.className = 'px-3 py-4 text-center text-sm text-slate-400';
        div.textContent = msg;
        return div;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
