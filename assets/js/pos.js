/* ==========================================================================
   Kasir POS — Terminal
   Cart state, pricing, payment and checkout for the cashier screen.
   Totals here mirror CheckoutService so the operator sees exactly what the
   server will charge; the server still recalculates before saving.
   ========================================================================== */

(function () {
    'use strict';

    const root = document.getElementById('pos-app');
    if (!root) return;

    const CFG = window.POS_CONFIG || {};
    const CART_KEY = 'kasir.cart.' + (CFG.cashierId || 'x');

    const fmt = window.posFormat;
    const money = (v) => fmt.money(v, CFG.currencySymbol);

    /* --- State ----------------------------------------------------------- */

    const state = {
        products: CFG.products || [],
        category: null,
        term: '',
        lines: [],            // { id, sku, name, unit, price, qty, discount, stock, trackStock, taxExempt }
        customer: null,
        discountType: 'none',
        discountValue: 0,
        tenders: [],          // { method, amount, reference }
        activeMethod: 'cash',
        entry: '',            // keypad buffer
        heldId: null,
        busy: false,
    };

    /* --- Element handles -------------------------------------------------- */

    const el = {
        grid: root.querySelector('[data-grid]'),
        rail: root.querySelector('[data-rail]'),
        scan: root.querySelector('[data-scan]'),
        search: root.querySelector('[data-search]'),
        lines: root.querySelector('[data-lines]'),
        totals: root.querySelector('[data-totals]'),
        // Two of these exist — the desktop header and the mobile sheet
        // handle — so both must be kept in step.
        counts: root.querySelectorAll('[data-cart-count]'),
        payBtn: root.querySelector('[data-pay]'),
        customerLabel: root.querySelector('[data-customer-label]'),
        cart: root.querySelector('.pos-cart'),
    };

    /* --- Pricing --------------------------------------------------------- */

    function unitPriceFor(line) {
        if (line.wholesalePrice && line.minWholesaleQty > 0 && line.qty >= line.minWholesaleQty) {
            return line.wholesalePrice;
        }
        return line.price;
    }

    function roundTotal(amount) {
        const step = { nearest_100: 100, nearest_500: 500, nearest_1000: 1000 }[CFG.roundingMode] || 0;
        if (!step) return Math.round(amount * 100) / 100;
        return Math.round(amount / step) * step;
    }

    function totals() {
        let subtotal = 0;

        const priced = state.lines.map((line) => {
            const gross = unitPriceFor(line) * line.qty;
            const discount = Math.min(line.discount || 0, gross);
            const lineTotal = gross - discount;
            subtotal += lineTotal;
            return { line, lineTotal };
        });

        let discountAmount = 0;
        if (state.discountType === 'percent') {
            discountAmount = subtotal * (Math.min(100, Math.max(0, state.discountValue)) / 100);
        } else if (state.discountType === 'amount') {
            discountAmount = Math.min(Math.max(0, state.discountValue), subtotal);
        }

        const afterDiscount = subtotal - discountAmount;
        const service = afterDiscount * ((CFG.serviceChargePercent || 0) / 100);

        // Tax is computed per line so exempt products stay exempt, with the
        // basket discount spread proportionally first.
        const rate = (CFG.taxPercent || 0) / 100;
        let tax = 0;

        if (CFG.taxEnabled && rate > 0) {
            priced.forEach(({ line, lineTotal }) => {
                if (line.taxExempt) return;

                const share = subtotal > 0 ? lineTotal / subtotal : 0;
                const net = lineTotal - discountAmount * share;

                tax += CFG.taxInclusive ? net - net / (1 + rate) : net * rate;
            });
        }

        tax = Math.round(tax * 100) / 100;

        const raw = afterDiscount + service + (CFG.taxEnabled && !CFG.taxInclusive ? tax : 0);
        const total = roundTotal(raw);

        return {
            subtotal,
            discountAmount,
            service,
            tax,
            rounding: total - raw,
            total,
            qty: state.lines.reduce((sum, l) => sum + l.qty, 0),
        };
    }

    function paidSoFar() {
        return state.tenders.reduce((sum, t) => sum + t.amount, 0);
    }

    /* --- Cart operations -------------------------------------------------- */

    function addProduct(product, qty) {
        qty = qty || 1;

        if (product.track_stock && product.stock <= 0 && !CFG.allowNegativeStock) {
            window.posToast('Stok ' + product.name + ' habis.', 'bad');
            return;
        }

        const existing = state.lines.find((l) => l.id === product.id);
        const nextQty = (existing ? existing.qty : 0) + qty;

        if (product.track_stock && !CFG.allowNegativeStock && nextQty > product.stock) {
            window.posToast('Stok ' + product.name + ' tersisa ' + fmt.qty(product.stock) + ' ' + product.unit + '.', 'bad');
            return;
        }

        if (existing) {
            existing.qty = nextQty;
        } else {
            state.lines.push({
                id: product.id,
                sku: product.sku,
                name: product.name,
                unit: product.unit,
                price: product.price,
                wholesalePrice: product.wholesale_price,
                minWholesaleQty: product.min_wholesale_qty,
                stock: product.stock,
                trackStock: product.track_stock,
                taxExempt: Boolean(product.tax_exempt),
                qty: qty,
                discount: 0,
            });
        }

        renderCart();
        persist();
    }

    function setQty(id, qty) {
        const line = state.lines.find((l) => l.id === id);
        if (!line) return;

        if (qty <= 0) {
            removeLine(id);
            return;
        }

        if (line.trackStock && !CFG.allowNegativeStock && qty > line.stock) {
            window.posToast('Stok tersisa ' + fmt.qty(line.stock) + ' ' + line.unit + '.', 'bad');
            qty = line.stock;
        }

        line.qty = qty;
        renderCart();
        persist();
    }

    function removeLine(id) {
        state.lines = state.lines.filter((l) => l.id !== id);
        renderCart();
        persist();
    }

    function clearCart(silent) {
        state.lines = [];
        state.customer = null;
        state.discountType = 'none';
        state.discountValue = 0;
        state.tenders = [];
        state.heldId = null;

        renderCart();
        persist();

        if (!silent) window.posToast('Keranjang dikosongkan.');
    }

    /* --- Persistence ------------------------------------------------------ */

    function persist() {
        try {
            localStorage.setItem(CART_KEY, JSON.stringify({
                lines: state.lines,
                customer: state.customer,
                discountType: state.discountType,
                discountValue: state.discountValue,
                heldId: state.heldId,
            }));
        } catch (e) { /* storage full or blocked — the cart still works */ }
    }

    function restore() {
        try {
            const raw = localStorage.getItem(CART_KEY);
            if (!raw) return;

            const saved = JSON.parse(raw);
            state.lines = saved.lines || [];
            state.customer = saved.customer || null;
            state.discountType = saved.discountType || 'none';
            state.discountValue = saved.discountValue || 0;
            state.heldId = saved.heldId || null;
        } catch (e) { /* ignore corrupt payload */ }
    }

    /* --- Rendering -------------------------------------------------------- */

    function visibleProducts() {
        const term = state.term.trim().toLowerCase();

        return state.products.filter((p) => {
            if (state.category && p.category_id !== state.category) return false;
            if (!term) return true;

            return p.name.toLowerCase().includes(term)
                || (p.sku || '').toLowerCase().includes(term)
                || (p.barcode || '').toLowerCase().includes(term);
        });
    }

    function renderGrid() {
        const items = visibleProducts();

        if (!items.length) {
            el.grid.innerHTML =
                '<div class="empty" style="grid-column:1/-1">' +
                '<div class="empty__title">Produk tidak ditemukan</div>' +
                '<div class="empty__text">Coba kata kunci lain, atau pindai barcode produk.</div>' +
                '</div>';
            return;
        }

        el.grid.innerHTML = items.map((p) => {
            const out = p.track_stock && p.stock <= 0;
            const low = p.track_stock && p.stock > 0 && p.stock <= 5;

            let flag = '';
            if (out) flag = '<span class="pos-item__flag pos-item__flag--out">Habis</span>';
            else if (low) flag = '<span class="pos-item__flag pos-item__flag--low">Sisa ' + fmt.qty(p.stock) + '</span>';
            else if (p.favorite) flag = '<span class="pos-item__flag pos-item__flag--fav">★</span>';

            return '' +
                '<button type="button" class="pos-item' + (out ? ' is-out' : '') + '"' +
                    ' style="' + tint(p.color) + '"' +
                    ' data-add="' + p.id + '"' + (out ? ' disabled' : '') + '>' +
                    '<span class="pos-item__accent" style="background:linear-gradient(90deg,'
                        + escapeAttr(p.color) + ',rgba(255,255,255,0))"></span>' +
                    flag +
                    '<span class="pos-item__name">' + escapeHtml(p.name) + '</span>' +
                    '<span class="pos-item__price">' + money(p.price) + '</span>' +
                    '<span class="pos-item__meta">' +
                        '<span>' + escapeHtml(p.category || '—') + '</span>' +
                        '<span>' + (p.track_stock ? fmt.qty(p.stock) + ' ' + escapeHtml(p.unit) : '∞') + '</span>' +
                    '</span>' +
                '</button>';
        }).join('');
    }

    function renderCart() {
        const t = totals();

        if (!state.lines.length) {
            el.lines.innerHTML =
                '<div class="empty" style="padding:44px 18px">' +
                '<div class="empty__title">Keranjang kosong</div>' +
                '<div class="empty__text">Pindai barcode atau pilih produk untuk memulai transaksi.</div>' +
                '</div>';
        } else {
            el.lines.innerHTML = state.lines.map((line) => {
                const unit = unitPriceFor(line);
                const gross = unit * line.qty;

                return '' +
                    '<div class="cart-line" data-line="' + line.id + '">' +
                        '<div class="cart-line__main">' +
                            '<div class="cart-line__name">' + escapeHtml(line.name) + '</div>' +
                            '<div class="cart-line__meta">' + money(unit) + ' × ' + fmt.qty(line.qty) + ' ' + escapeHtml(line.unit) + '</div>' +
                            (line.discount > 0 ? '<div class="cart-line__discount">Diskon ' + money(line.discount) + '</div>' : '') +
                            '<div class="row g-6 mt-8">' +
                                '<div class="qty-stepper">' +
                                    '<button type="button" data-dec="' + line.id + '">−</button>' +
                                    '<input type="number" step="any" min="0" value="' + line.qty + '" data-qty="' + line.id + '">' +
                                    '<button type="button" data-inc="' + line.id + '">+</button>' +
                                '</div>' +
                                '<button type="button" class="btn btn--ghost btn--sm" data-line-discount="' + line.id + '" title="Diskon item">%</button>' +
                                '<button type="button" class="btn btn--ghost btn--sm" data-remove="' + line.id + '" title="Hapus">✕</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="cart-line__total">' + money(gross - (line.discount || 0)) + '</div>' +
                    '</div>';
            }).join('');
        }

        const countLabel = state.lines.length
            ? state.lines.length + ' item · ' + money(t.total)
            : 'Kosong';

        el.counts.forEach((node) => { node.textContent = countLabel; });

        const rows = [
            ['Subtotal', money(t.subtotal), ''],
        ];

        if (t.discountAmount > 0) rows.push(['Diskon', '− ' + money(t.discountAmount), 'total-row--discount']);
        if (t.service > 0) rows.push(['Layanan ' + CFG.serviceChargePercent + '%', money(t.service), '']);
        if (CFG.taxEnabled && t.tax > 0) {
            rows.push([
                'PPN ' + CFG.taxPercent + '%' + (CFG.taxInclusive ? ' (termasuk)' : ''),
                money(t.tax),
                '',
            ]);
        }
        if (Math.abs(t.rounding) > 0.004) rows.push(['Pembulatan', money(t.rounding), '']);

        el.totals.innerHTML =
            rows.map(([label, value, cls]) =>
                '<div class="total-row ' + cls + '"><span>' + label + '</span><span>' + value + '</span></div>'
            ).join('') +
            '<div class="total-row total-row--grand"><span>Total</span><span>' + money(t.total) + '</span></div>';

        el.payBtn.disabled = state.lines.length === 0;

        if (el.customerLabel) {
            el.customerLabel.textContent = state.customer ? state.customer.name : 'Pelanggan Umum';
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '');
    }

    /**
     * Turn a category's hex colour into the soft tints a product tile uses.
     * Computed here rather than with CSS color-mix so the tiles render the
     * same on older terminal browsers.
     */
    function tint(hex) {
        var m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
        var r = 100, g = 116, b = 139;   // slate fallback

        if (m) {
            var n = parseInt(m[1], 16);
            r = (n >> 16) & 255;
            g = (n >> 8) & 255;
            b = n & 255;
        }

        return '--cat:rgb(' + r + ',' + g + ',' + b + ');'
            + '--cat-soft:rgba(' + r + ',' + g + ',' + b + ',.13);'
            + '--cat-line:rgba(' + r + ',' + g + ',' + b + ',.55);'
            + '--cat-glow:rgba(' + r + ',' + g + ',' + b + ',.38);';
    }

    /* --- Barcode scanning -------------------------------------------------- */

    /**
     * Resolve whatever landed in the scan box.
     *
     * The field accepts two very different things: an exact code typed by a
     * hardware scanner, and a product name typed by a person. Both have to
     * work, so exact codes are tried first, then names. An ambiguous name is
     * never guessed at — the grid is narrowed so the cashier picks.
     */
    function handleScan(code) {
        code = code.trim();
        if (!code) return;

        // 1. Exact code, straight from the preloaded catalogue — no round trip.
        const exact = state.products.find(
            (p) => p.barcode === code || p.sku === code
        );

        if (exact) {
            addProduct(exact);
            el.scan.value = '';
            return;
        }

        // 2. A typed name, matched against the same slice.
        const term = code.toLowerCase();
        const byName = state.products.filter(
            (p) => p.name.toLowerCase().includes(term)
        );

        if (byName.length === 1) {
            addProduct(byName[0]);
            el.scan.value = '';
            return;
        }

        if (byName.length > 1) {
            narrowTo(code);
            window.posToast(byName.length + ' produk cocok dengan "' + code + '". Pilih dari daftar.');
            el.scan.value = '';
            return;
        }

        // 3. Nothing here — the catalogue may be bigger than the slice held.
        fetch(CFG.urls.lookup + '?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json' },
        })
            .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (data.ambiguous) {
                    (data.products || []).forEach(cacheProduct);
                    narrowTo(code);
                    window.posToast(data.message);
                    return;
                }

                if (!ok || !data.found) {
                    window.posToast(data.message || 'Produk tidak ditemukan.', 'bad');
                    return;
                }

                cacheProduct(data.product);
                addProduct(data.product);
            })
            .catch(() => window.posToast('Gagal menghubungi server.', 'bad'))
            .finally(() => { el.scan.value = ''; });
    }

    /** Keep a product the server returned, so the next scan of it is instant. */
    function cacheProduct(product) {
        if (!state.products.some((p) => p.id === product.id)) {
            state.products.push(product);
        }
    }

    /** Filter the grid to a term and mirror it into the filter box. */
    function narrowTo(term) {
        state.term = term;
        if (el.search) el.search.value = term;
        renderGrid();
    }

    /* --- Payment ---------------------------------------------------------- */

    const pay = {
        open() {
            if (!state.lines.length) return;

            state.tenders = [];
            state.entry = '';
            state.activeMethod = 'cash';

            this.render();
            window.posModal.open('payment-modal');

            // The scan field normally holds focus; if it kept it here every
            // digit typed would land in the barcode box and Enter would fire
            // a product lookup instead of taking payment.
            el.scan.blur();
        },

        render() {
            const t = totals();
            const paid = paidSoFar();
            const entered = Number(state.entry || 0);
            const outstanding = Math.max(0, t.total - paid);

            root.querySelector('[data-due]').textContent = money(t.total);
            root.querySelector('[data-outstanding]').textContent = money(outstanding);

            const amountField = root.querySelector('[data-amount]');
            amountField.value = state.entry ? Number(state.entry).toLocaleString('id-ID') : '';
            amountField.placeholder = money(outstanding);

            // Change is only meaningful once the bill is fully covered.
            const covered = paid + entered;
            const change = covered - t.total;

            const box = root.querySelector('[data-change-box]');
            const label = root.querySelector('[data-change-label]');
            const value = root.querySelector('[data-change-value]');

            if (change >= 0) {
                box.classList.remove('change-box--short');
                label.textContent = 'Kembalian';
                value.textContent = money(change);
            } else {
                box.classList.add('change-box--short');
                label.textContent = 'Kurang Bayar';
                value.textContent = money(Math.abs(change));
            }

            // Tender list for split payments.
            const list = root.querySelector('[data-tenders]');
            list.innerHTML = state.tenders.length
                ? state.tenders.map((tender, index) =>
                    '<div class="tender-row">' +
                        '<span class="grow">' + escapeHtml(CFG.paymentMethods[tender.method] || tender.method) + '</span>' +
                        '<span class="bold">' + money(tender.amount) + '</span>' +
                        '<button type="button" class="btn btn--ghost btn--sm" data-drop-tender="' + index + '">✕</button>' +
                    '</div>'
                ).join('')
                : '';

            root.querySelectorAll('[data-method]').forEach((btn) => {
                btn.classList.toggle('is-active', btn.getAttribute('data-method') === state.activeMethod);
            });

            // Quick-cash suggestions are only useful for cash.
            root.querySelector('[data-cash-only]').style.display = state.activeMethod === 'cash' ? '' : 'none';

            const confirmBtn = root.querySelector('[data-confirm-pay]');
            confirmBtn.disabled = covered + 0.001 < t.total || state.busy;
        },

        press(key) {
            if (key === 'clear') state.entry = '';
            else if (key === 'back') state.entry = state.entry.slice(0, -1);
            else if (key === '000') state.entry = (state.entry || '') === '' ? '' : state.entry + '000';
            else state.entry = (state.entry + key).replace(/^0+(?=\d)/, '');

            this.render();
        },

        setExact() {
            const outstanding = Math.max(0, totals().total - paidSoFar());
            state.entry = String(Math.round(outstanding));
            this.render();
        },

        addTender() {
            const amount = Number(state.entry || 0);
            const outstanding = Math.max(0, totals().total - paidSoFar());

            if (amount <= 0) {
                window.posToast('Masukkan nominal pembayaran.', 'bad');
                return;
            }

            state.tenders.push({
                method: state.activeMethod,
                amount: amount,
                reference: null,
            });

            state.entry = '';

            if (paidSoFar() < outstanding) {
                window.posToast('Sisa tagihan ' + money(Math.max(0, totals().total - paidSoFar())));
            }

            this.render();
        },

        submit() {
            if (state.busy) return;

            const t = totals();

            // A single full-amount tender does not need to be added manually.
            if (!state.tenders.length) {
                const entered = Number(state.entry || 0);

                if (entered + 0.001 < t.total) {
                    window.posToast('Pembayaran belum mencukupi.', 'bad');
                    return;
                }

                state.tenders.push({ method: state.activeMethod, amount: entered, reference: null });
            }

            if (paidSoFar() + 0.001 < t.total) {
                window.posToast('Pembayaran belum mencukupi.', 'bad');
                return;
            }

            state.busy = true;
            this.render();
            progress(35);

            fetch(CFG.urls.checkout, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CFG.csrf,
                },
                body: JSON.stringify({
                    items: state.lines.map((l) => ({
                        product_id: l.id,
                        qty: l.qty,
                        discount_amount: l.discount || 0,
                    })),
                    discount_type: state.discountType,
                    discount_value: state.discountValue,
                    payments: state.tenders,
                    customer_id: state.customer ? state.customer.id : null,
                    held_order_id: state.heldId,
                }),
            })
                .then((r) => r.json().then((data) => ({ ok: r.ok, status: r.status, data })))
                .then(({ ok, data }) => {
                    progress(100);

                    if (!ok) {
                        const message = data.message
                            || (data.errors && Object.values(data.errors)[0][0])
                            || 'Transaksi gagal diproses.';

                        window.posToast(message, 'bad');

                        // Let the operator adjust and retry with a clean slate.
                        state.tenders = [];
                        return;
                    }

                    this.success(data);
                })
                .catch(() => window.posToast('Gagal menghubungi server. Periksa koneksi.', 'bad'))
                .finally(() => {
                    state.busy = false;
                    this.render();
                    setTimeout(() => progress(0), 400);
                });
        },

        success(sale) {
            window.posModal.close('payment-modal');

            root.querySelector('[data-done-invoice]').textContent = sale.invoice_number;
            root.querySelector('[data-done-total]').textContent = money(sale.total);
            root.querySelector('[data-done-change]').textContent = money(sale.change);
            root.querySelector('[data-print-receipt]').setAttribute('href', sale.receipt_url);

            window.posModal.open('done-modal');

            // Stock in the local catalogue follows the sale, so the grid shows
            // the right numbers without a page reload.
            state.lines.forEach((line) => {
                const product = state.products.find((p) => p.id === line.id);
                if (product && product.track_stock) product.stock -= line.qty;
            });

            clearCart(true);
            renderGrid();

            if (CFG.autoPrint) {
                window.open(sale.receipt_url, 'struk', 'width=420,height=680');
            }
        },
    };

    function progress(percent) {
        const bar = document.querySelector('.progress-top');
        if (bar) bar.style.width = percent + '%';
    }

    /* --- Hold / resume ---------------------------------------------------- */

    function holdCart() {
        if (!state.lines.length) return;

        const t = totals();

        fetch(CFG.urls.hold, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CFG.csrf,
            },
            body: JSON.stringify({
                label: state.customer ? state.customer.name : null,
                payload: {
                    items: state.lines,
                    customer: state.customer,
                    discountType: state.discountType,
                    discountValue: state.discountValue,
                },
                item_count: state.lines.length,
                total: t.total,
            }),
        })
            .then((r) => r.json())
            .then(() => {
                window.posToast('Transaksi ditahan.', 'ok');
                clearCart(true);
                loadHeld();
            })
            .catch(() => window.posToast('Gagal menahan transaksi.', 'bad'));
    }

    function loadHeld() {
        fetch(CFG.urls.heldList, { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                const list = root.querySelector('[data-held-list]');
                const badge = root.querySelector('[data-held-count]');

                if (badge) {
                    badge.textContent = data.held.length;
                    badge.style.display = data.held.length ? '' : 'none';
                }

                if (!list) return;

                list.innerHTML = data.held.length
                    ? data.held.map((h) =>
                        '<div class="between" style="padding:11px 0;border-bottom:1px solid var(--border)">' +
                            '<div>' +
                                '<div class="semi">' + escapeHtml(h.label || h.reference) + '</div>' +
                                '<div class="tiny muted">' + h.item_count + ' item · ' + money(h.total) + '</div>' +
                            '</div>' +
                            '<div class="row g-6">' +
                                '<button type="button" class="btn btn--soft btn--sm" data-resume=\'' + escapeAttr(JSON.stringify(h)) + '\'>Lanjutkan</button>' +
                                '<button type="button" class="btn btn--ghost btn--sm" data-drop-held="' + h.id + '">✕</button>' +
                            '</div>' +
                        '</div>'
                    ).join('')
                    : '<div class="empty"><div class="empty__title">Tidak ada transaksi ditahan</div></div>';
            })
            .catch(() => { /* non-critical */ });
    }

    function resumeHeld(held) {
        if (state.lines.length && !window.confirm('Keranjang saat ini akan diganti. Lanjutkan?')) return;

        const payload = held.payload || {};

        state.lines = payload.items || [];
        state.customer = payload.customer || null;
        state.discountType = payload.discountType || 'none';
        state.discountValue = payload.discountValue || 0;
        state.heldId = held.id;

        renderCart();
        persist();
        window.posModal.close('held-modal');
        window.posToast('Transaksi dilanjutkan.');
    }

    /* --- Events ----------------------------------------------------------- */

    root.addEventListener('click', (event) => {
        const t = event.target;
        const closest = (attr) => t.closest('[' + attr + ']');

        let node;

        if ((node = closest('data-add'))) {
            const id = Number(node.getAttribute('data-add'));
            const product = state.products.find((p) => p.id === id);
            if (product) addProduct(product);
            return;
        }

        if ((node = closest('data-inc'))) { const id = Number(node.getAttribute('data-inc')); const l = state.lines.find((x) => x.id === id); setQty(id, l.qty + 1); return; }
        if ((node = closest('data-dec'))) { const id = Number(node.getAttribute('data-dec')); const l = state.lines.find((x) => x.id === id); setQty(id, l.qty - 1); return; }
        if ((node = closest('data-remove'))) { removeLine(Number(node.getAttribute('data-remove'))); return; }

        if ((node = closest('data-line-discount'))) {
            const id = Number(node.getAttribute('data-line-discount'));
            const line = state.lines.find((l) => l.id === id);
            const input = window.prompt('Diskon untuk ' + line.name + ' (dalam rupiah):', line.discount || 0);

            if (input !== null) {
                line.discount = Math.max(0, Number(input) || 0);
                renderCart();
                persist();
            }
            return;
        }

        if ((node = closest('data-category'))) {
            const value = node.getAttribute('data-category');
            state.category = value === '' ? null : Number(value);

            root.querySelectorAll('[data-category]').forEach((b) => b.classList.remove('is-active'));
            node.classList.add('is-active');

            renderGrid();
            return;
        }

        if (closest('data-pay')) { pay.open(); return; }
        if (closest('data-clear-cart')) { if (state.lines.length && window.confirm('Kosongkan keranjang?')) clearCart(); return; }
        if (closest('data-hold')) { holdCart(); return; }
        if (closest('data-open-held')) { loadHeld(); window.posModal.open('held-modal'); return; }

        if ((node = closest('data-method'))) {
            state.activeMethod = node.getAttribute('data-method');
            pay.render();
            return;
        }

        if ((node = closest('data-key'))) { pay.press(node.getAttribute('data-key')); return; }
        if ((node = closest('data-quick'))) { state.entry = node.getAttribute('data-quick'); pay.render(); return; }
        if (closest('data-exact')) { pay.setExact(); return; }
        if (closest('data-add-tender')) { pay.addTender(); return; }
        if (closest('data-confirm-pay')) { pay.submit(); return; }

        if ((node = closest('data-drop-tender'))) {
            state.tenders.splice(Number(node.getAttribute('data-drop-tender')), 1);
            pay.render();
            return;
        }

        if ((node = closest('data-resume'))) {
            resumeHeld(JSON.parse(node.getAttribute('data-resume')));
            return;
        }

        if ((node = closest('data-drop-held'))) {
            const id = node.getAttribute('data-drop-held');

            fetch(CFG.urls.hold + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' },
            }).then(() => loadHeld());
            return;
        }

        if (closest('data-new-sale')) {
            window.posModal.close('done-modal');
            focusScan();
            return;
        }

        if (closest('data-cart-toggle')) {
            el.cart.classList.toggle('is-open');
            return;
        }
    });

    root.addEventListener('change', (event) => {
        const node = event.target.closest('[data-qty]');
        if (node) setQty(Number(node.getAttribute('data-qty')), Number(node.value));
    });

    root.addEventListener('input', (event) => {
        if (event.target.hasAttribute('data-search')) {
            state.term = event.target.value;
            renderGrid();
        }
    });

    // The scanner ends its transmission with Enter.
    el.scan.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleScan(el.scan.value);
        }
    });

    function focusScan() {
        setTimeout(() => el.scan.focus(), 40);
    }

    /* --- Keyboard shortcuts ------------------------------------------------ */

    document.addEventListener('keydown', (event) => {
        // While taking payment the keyboard drives the amount field, so the
        // dialog claims these keys before anything else can act on them.
        if (document.getElementById('payment-modal').classList.contains('is-open')) {
            if (/^[0-9]$/.test(event.key)) { event.preventDefault(); pay.press(event.key); return; }

            switch (event.key) {
                case 'Backspace': event.preventDefault(); pay.press('back'); return;
                case 'Delete':    event.preventDefault(); pay.press('clear'); return;
                case '+':         event.preventDefault(); pay.addTender(); return;
                case 'Enter':     event.preventDefault(); pay.submit(); return;
                default: return;
            }
        }

        if (event.key === 'F2') { event.preventDefault(); focusScan(); return; }
        if (event.key === 'F4') { event.preventDefault(); pay.open(); return; }
        if (event.key === 'F9') { event.preventDefault(); holdCart(); return; }
    });

    /* --- Boot -------------------------------------------------------------- */

    restore();
    renderGrid();
    renderCart();
    loadHeld();
    focusScan();

    // Hand the keyboard back to the scanner once a dialog is dismissed,
    // including via Escape where no click happens to restore it.
    ['payment-modal', 'done-modal', 'held-modal'].forEach((id) => {
        document.getElementById(id)?.addEventListener('modal:close', focusScan);
    });

    // Keep the scan field ready whenever the operator clicks empty space.
    document.addEventListener('click', (event) => {
        if (document.querySelector('.modal.is-open')) return;
        if (event.target.closest('input, textarea, select, button, a')) return;
        focusScan();
    });

    // Live clock in the header.
    const clock = root.querySelector('[data-clock]');
    if (clock) {
        const tick = () => {
            clock.textContent = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            });
        };
        tick();
        setInterval(tick, 1000);
    }
})();
