/* ==========================================================================
   Kasir POS — UI runtime
   Small, dependency-free behaviours wired up by data attributes.
   ========================================================================== */

(function () {
    'use strict';

    /* --- Theme ----------------------------------------------------------- */

    const THEME_KEY = 'kasir.theme';

    const Theme = {
        apply(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.querySelectorAll('[data-theme-label]').forEach((el) => {
                el.textContent = theme === 'dark' ? 'Mode Terang' : 'Mode Gelap';
            });
        },
        current() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        },
        toggle() {
            const next = this.current() === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* private mode */ }
            this.apply(next);
        },
    };

    // Applied as early as possible in <head> too, to avoid a flash of light.
    try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored) Theme.apply(stored);
    } catch (e) { /* ignore */ }

    /* --- Toasts ---------------------------------------------------------- */

    function toast(message, variant) {
        let host = document.querySelector('.toasts');

        if (!host) {
            host = document.createElement('div');
            host.className = 'toasts';
            document.body.appendChild(host);
        }

        const el = document.createElement('div');
        el.className = 'toast' + (variant ? ' toast--' + variant : '');
        el.textContent = message;
        host.appendChild(el);

        setTimeout(() => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 200);
        }, 3600);
    }

    window.posToast = toast;

    /* --- Modals ---------------------------------------------------------- */

    const Modal = {
        open(id) {
            const el = document.getElementById(id);
            if (!el) return;

            el.classList.add('is-open');
            document.body.style.overflow = 'hidden';

            const focusable = el.querySelector('[data-autofocus]');
            if (focusable) setTimeout(() => focusable.focus(), 60);

            el.dispatchEvent(new CustomEvent('modal:open'));
        },
        close(id) {
            const el = document.getElementById(id);
            if (!el) return;

            el.classList.remove('is-open');

            if (!document.querySelector('.modal.is-open')) {
                document.body.style.overflow = '';
            }

            el.dispatchEvent(new CustomEvent('modal:close'));
        },
        closeAll() {
            document.querySelectorAll('.modal.is-open').forEach((el) => el.classList.remove('is-open'));
            document.body.style.overflow = '';
        },
    };

    window.posModal = Modal;

    /* --- Delegated events ------------------------------------------------ */

    document.addEventListener('click', (event) => {
        const target = event.target;

        // Theme
        if (target.closest('[data-theme-toggle]')) {
            event.preventDefault();
            Theme.toggle();
            return;
        }

        // Sidebar (mobile)
        if (target.closest('[data-sidebar-toggle]')) {
            event.preventDefault();
            document.querySelector('.sidebar')?.classList.toggle('is-open');
            document.querySelector('.sidebar-scrim')?.classList.toggle('is-open');
            return;
        }

        if (target.closest('.sidebar-scrim')) {
            document.querySelector('.sidebar')?.classList.remove('is-open');
            document.querySelector('.sidebar-scrim')?.classList.remove('is-open');
            return;
        }

        // Dropdowns — only one open at a time.
        const trigger = target.closest('[data-dropdown]');
        if (trigger) {
            event.preventDefault();
            const parent = trigger.closest('.dropdown');
            const wasOpen = parent.classList.contains('is-open');

            document.querySelectorAll('.dropdown.is-open').forEach((d) => d.classList.remove('is-open'));
            if (!wasOpen) parent.classList.add('is-open');
            return;
        }

        if (!target.closest('.dropdown__menu')) {
            document.querySelectorAll('.dropdown.is-open').forEach((d) => d.classList.remove('is-open'));
        }

        // Modals
        const opener = target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            Modal.open(opener.getAttribute('data-modal-open'));

            // Let a trigger seed the dialog's fields: data-fill='{"name":"x"}'
            const fill = opener.getAttribute('data-fill');
            if (fill) {
                const dialog = document.getElementById(opener.getAttribute('data-modal-open'));
                try {
                    const values = JSON.parse(fill);
                    Object.keys(values).forEach((key) => {
                        const input = dialog.querySelector('[name="' + key + '"]');
                        if (!input) return;

                        if (input.type === 'checkbox') {
                            input.checked = Boolean(values[key]);
                        } else {
                            input.value = values[key] === null ? '' : values[key];
                        }
                    });
                } catch (e) { /* malformed payload — leave the form blank */ }

                const action = opener.getAttribute('data-action-url');
                if (action) dialog.querySelector('form')?.setAttribute('action', action);
            }
            return;
        }

        const closer = target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            const dialog = closer.closest('.modal');
            if (dialog) Modal.close(dialog.id);
            return;
        }

        if (target.classList.contains('modal__backdrop')) {
            Modal.close(target.closest('.modal').id);
            return;
        }

        // Tabs
        const tab = target.closest('[data-tab]');
        if (tab) {
            event.preventDefault();
            const name = tab.getAttribute('data-tab');
            const scope = tab.closest('[data-tabs]') || document;

            scope.querySelectorAll('[data-tab]').forEach((t) => t.classList.remove('is-active'));
            scope.querySelectorAll('[data-tab-panel]').forEach((p) => p.classList.remove('is-active'));

            tab.classList.add('is-active');
            scope.querySelector('[data-tab-panel="' + name + '"]')?.classList.add('is-active');

            if (history.replaceState) {
                history.replaceState(null, '', '#' + name);
            }
            return;
        }

        // Destructive actions ask first.
        const confirmer = target.closest('[data-confirm]');
        if (confirmer) {
            if (!window.confirm(confirmer.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            Modal.closeAll();
            document.querySelectorAll('.dropdown.is-open').forEach((d) => d.classList.remove('is-open'));
        }
    });

    /* --- Progressive enhancement on load --------------------------------- */

    document.addEventListener('DOMContentLoaded', () => {
        // Re-open the tab named in the URL hash.
        if (window.location.hash) {
            const el = document.querySelector('[data-tab="' + window.location.hash.slice(1) + '"]');
            if (el) el.click();
        }

        // Forms that filter a list submit themselves when a control changes.
        document.querySelectorAll('[data-auto-submit]').forEach((form) => {
            form.querySelectorAll('select, input[type="date"]').forEach((input) => {
                input.addEventListener('change', () => form.submit());
            });
        });

        // Debounced search-as-you-type.
        document.querySelectorAll('[data-search-form]').forEach((form) => {
            const input = form.querySelector('input[name="q"]');
            if (!input) return;

            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 480);
            });
        });

        // Live preview of the product-ID pattern on the settings screen.
        const skuForm = document.querySelector('[data-sku-form]');
        if (skuForm) {
            const preview = document.querySelector('[data-sku-preview]');
            const pattern = document.querySelector('[data-sku-pattern]');
            const url = skuForm.getAttribute('data-preview-url');
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            let timer;
            const refresh = () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    const body = new FormData(skuForm);

                    fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body,
                    })
                        .then((r) => r.json())
                        .then((data) => {
                            if (preview) preview.textContent = data.preview;
                            if (pattern) pattern.textContent = data.pattern;
                        })
                        .catch(() => { /* preview is cosmetic — stay quiet */ });
                }, 320);
            };

            skuForm.querySelectorAll('input, select').forEach((input) => {
                input.addEventListener('input', refresh);
                input.addEventListener('change', refresh);
            });
        }

        // Select-all checkbox in tables (label printing).
        document.querySelectorAll('[data-check-all]').forEach((master) => {
            const scope = master.closest('[data-check-scope]') || document;

            master.addEventListener('change', () => {
                scope.querySelectorAll('[data-check-item]').forEach((box) => {
                    box.checked = master.checked;
                });
            });
        });

        // Show the chosen filename next to a file input.
        document.querySelectorAll('input[type="file"][data-file-name]').forEach((input) => {
            input.addEventListener('change', () => {
                const label = document.querySelector(input.getAttribute('data-file-name'));
                if (label) label.textContent = input.files[0]?.name || 'Belum ada file dipilih';
            });
        });

        // Flash messages arrive as data attributes on <body>.
        const flash = document.body.getAttribute('data-flash');
        if (flash) toast(flash, document.body.getAttribute('data-flash-type') || 'ok');
    });

    /* --- Helpers used by inline scripts ---------------------------------- */

    window.posFormat = {
        money(value, symbol) {
            const amount = Math.round((Number(value) || 0) * 100) / 100;
            const hasCents = Math.abs(amount % 1) > 0.001;

            const formatted = amount.toLocaleString('id-ID', {
                minimumFractionDigits: hasCents ? 2 : 0,
                maximumFractionDigits: hasCents ? 2 : 0,
            });

            return (symbol || 'Rp') + ' ' + formatted;
        },
        qty(value) {
            const amount = Number(value) || 0;
            return Number.isInteger(amount) ? String(amount) : String(amount).replace('.', ',');
        },
    };
})();
