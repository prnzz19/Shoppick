import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    setupGlobalState();
    setupSearchAutocomplete();
    setupFlashToast();
});

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
            ...(options.body ? {} : { 'Content-Type': 'application/json' }),
        },
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

window.toggleWishlist = async function (event, productId) {
    event.preventDefault();
    event.stopPropagation();
    try {
        const data = await api('/wishlist/toggle', { method: 'POST', body: { product_id: productId } });
        if (data.added) {
            window.showToast('Added to wishlist', 'success');
        } else {
            window.showToast('Removed from wishlist', 'info');
        }
        const btn = event.currentTarget;
        if (btn) btn.classList.toggle('text-rose-500');
    } catch (e) {
        if (e.includes && e.includes('Login')) {
            window.location.href = '/login';
        }
        window.showToast(e.message || 'Something went wrong', 'error');
    }
};

window.quickAdd = async function (event, productId) {
    event.preventDefault();
    event.stopPropagation();
    try {
        const form = new FormData();
        form.append('product_id', productId);
        form.append('quantity', 1);
        const data = await api('/cart/add', { method: 'POST', body: form });
        window.showToast('Added to cart', 'success');
        refreshCartBadge(data.cart_count);
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        if (e === 'Unauthenticated.') { window.location.href = '/login'; return; }
        window.showToast(e.message || 'Please login to add to cart', 'error');
        window.location.href = '/login';
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
