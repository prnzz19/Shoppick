document.addEventListener('alpine:init', () => {
    Alpine.data('voucherProductPicker', (config) => ({
        scope: config.scope,
        selectedProducts: config.selectedProducts || [],
        draftProducts: [],
        categories: config.categories || [],
        endpoint: config.endpoint,
        pickerId: config.pickerId,
        type: config.type || 'fixed',
        title: config.title || '',
        code: config.code || '',
        discountValue: config.discountValue || '',
        minPurchase: config.minPurchase || '0',
        maxDiscount: config.maxDiscount || '',
        usageLimit: config.usageLimit || '',
        perUserLimit: config.perUserLimit || '1',
        startsAt: config.startsAt || '',
        endsAt: config.endsAt || '',
        status: config.status || 'active',
        modalOpen: false,
        search: '',
        category: '',
        products: [],
        currentPage: 1,
        lastPage: 1,
        from: null,
        to: null,
        total: 0,
        loading: false,
        loadError: '',
        requestController: null,

        init() {
            if (this.scope === 'entire_shop') {
                this.selectedProducts = [];
            }
        },

        get visibleSelectedProducts() {
            return this.selectedProducts.slice(0, 3);
        },

        get hiddenSelectedCount() {
            return Math.max(0, this.selectedProducts.length - 3);
        },

        setScope(value) {
            this.scope = value;
            if (value === 'entire_shop') {
                this.selectedProducts = [];
            }
        },

        openPicker() {
            this.draftProducts = [...this.selectedProducts];
            this.search = '';
            this.category = '';
            this.modalOpen = true;
            this.loadProducts(1);
            this.$nextTick(() => this.$refs.productSearch?.focus());
        },

        closePicker() {
            this.modalOpen = false;
            this.draftProducts = [...this.selectedProducts];
        },

        applySelection() {
            this.selectedProducts = [...this.draftProducts];
            this.closePicker();
        },

        async loadProducts(page = 1) {
            this.requestController?.abort();
            const controller = new AbortController();
            this.requestController = controller;
            this.loading = true;
            this.loadError = '';
            const params = new URLSearchParams({ page: String(page) });
            const search = this.search.trim();
            if (search) params.set('q', search);
            if (this.category) params.set('category', this.category);

            try {
                const response = await fetch(`${this.endpoint}?${params}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    throw new Error(`Product search failed (${response.status}).`);
                }

                const result = await response.json();
                this.products = result.data;
                this.currentPage = result.current_page;
                this.lastPage = result.last_page;
                this.from = result.from;
                this.to = result.to;
                this.total = result.total;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.loadError = error.message || 'Unable to load products.';
                }
            } finally {
                if (!controller.signal.aborted) {
                    this.loading = false;
                }
            }
        },

        isSelected(productId) {
            return this.draftProducts.some((product) => Number(product.id) === Number(productId));
        },

        toggleProduct(product, checked) {
            if (checked && !this.isSelected(product.id)) {
                this.draftProducts = [...this.draftProducts, product];
            } else if (!checked) {
                this.draftProducts = this.draftProducts.filter((item) => Number(item.id) !== Number(product.id));
            }
        },

        removeProduct(productId) {
            this.selectedProducts = this.selectedProducts.filter((product) => Number(product.id) !== Number(productId));
            this.draftProducts = this.draftProducts.filter((product) => Number(product.id) !== Number(productId));
        },
    }));
});
