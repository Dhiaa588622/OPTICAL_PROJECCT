import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

const offlineQueueKey = 'optical-erp-offline-queue';

const offlineQueue = () => JSON.parse(localStorage.getItem(offlineQueueKey) || '[]');
const updateConnectionStatus = () => {
    const badge = document.querySelector('[data-connection-status]');
    const count = document.querySelector('[data-offline-count]');
    const queued = offlineQueue().length;
    if (badge) {
        badge.dataset.state = navigator.onLine ? 'online' : 'offline';
        badge.textContent = navigator.onLine ? badge.dataset.onlineLabel : badge.dataset.offlineLabel;
    }
    if (count) {
        count.textContent = queued;
        count.hidden = queued === 0;
    }
};

const queueForm = (form) => {
    const payload = {};
    new FormData(form).forEach((value, key) => {
        if (key !== '_token' && !(value instanceof File)) payload[key] = value;
    });
    const queue = offlineQueue();
    const uuid = crypto.randomUUID();
    const module = location.pathname.startsWith('/sales') ? 'pos' : (location.pathname.startsWith('/inventory') ? 'inventory' : 'forms');
    queue.push({
        uuid,
        module,
        form_key: form.dataset.draftKey || form.action,
        payload,
        idempotency_key: uuid,
        queued_at: new Date().toISOString(),
    });
    localStorage.setItem(offlineQueueKey, JSON.stringify(queue));
    form.dataset.offlineQueued = 'true';
    updateConnectionStatus();
};

const syncOfflineQueue = async () => {
    if (!navigator.onLine) return;
    const queue = offlineQueue();
    const remaining = [];
    for (const draft of queue) {
        try {
            const response = await fetch('/offline-drafts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(draft),
            });
            if (!response.ok && response.status !== 422) remaining.push(draft);
        } catch {
            remaining.push(draft);
        }
    }
    localStorage.setItem(offlineQueueKey, JSON.stringify(remaining));
    updateConnectionStatus();
};

document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.querySelector('[data-loading-overlay]');
    const sidebar = document.querySelector('.sidebar');

    document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.hasAttribute('data-clear-private-cache')) {
                navigator.serviceWorker?.controller?.postMessage('CLEAR_PRIVATE_CACHE');
                localStorage.removeItem(offlineQueueKey);
            }
            if (!navigator.onLine && form.dataset.draftKey) {
                event.preventDefault();
                queueForm(form);
                return;
            }
            if (overlay) {
                overlay.classList.add('active');
            }
        });
    });

    document.querySelectorAll('[data-wizard]').forEach((wizard) => {
        const buttons = [...wizard.querySelectorAll('[data-wizard-step]')];
        const panels = [...wizard.querySelectorAll('[data-wizard-panel]')];
        const progress = wizard.querySelector('[data-wizard-progress]');
        const draftKey = wizard.getAttribute('data-draft-key');

        const openStep = (index) => {
            const safeIndex = Math.max(0, Math.min(index, panels.length - 1));
            buttons.forEach((item, itemIndex) => item.classList.toggle('active', itemIndex === safeIndex));
            panels.forEach((panel, panelIndex) => panel.classList.toggle('active', panelIndex === safeIndex));
            if (progress) progress.style.width = `${((safeIndex + 1) / panels.length) * 100}%`;
        };

        const currentIndex = () => panels.findIndex((panel) => panel.classList.contains('active'));

        const validatePanel = (panel) => {
            const fields = [...panel.querySelectorAll('input, select, textarea')];
            const invalid = fields.find((field) => !field.checkValidity());
            if (invalid) {
                invalid.reportValidity();
                invalid.focus();
                return false;
            }
            return true;
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetIndex = buttons.indexOf(button);
                const activeIndex = currentIndex();
                if (targetIndex <= activeIndex || validatePanel(panels[activeIndex])) openStep(targetIndex);
            });
        });

        wizard.querySelectorAll('[data-wizard-next]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = currentIndex();
                if (validatePanel(panels[index])) openStep(index + 1);
            });
        });

        wizard.querySelectorAll('[data-wizard-back]').forEach((button) => {
            button.addEventListener('click', () => openStep(currentIndex() - 1));
        });

        if (draftKey) {
            const saved = JSON.parse(localStorage.getItem(draftKey) || '{}');
            wizard.querySelectorAll('input, select, textarea').forEach((field) => {
                if (field.name && saved[field.name] !== undefined && field.type !== 'file') field.value = saved[field.name];
                field.addEventListener('input', () => {
                    const draft = {};
                    wizard.querySelectorAll('input, select, textarea').forEach((input) => {
                        if (input.name && input.type !== 'file' && input.type !== 'hidden') draft[input.name] = input.value;
                    });
                    localStorage.setItem(draftKey, JSON.stringify(draft));
                });
            });
            wizard.closest('form')?.addEventListener('submit', () => localStorage.removeItem(draftKey));
        }

        openStep(Math.max(0, currentIndex()));
    });

    document.querySelectorAll('[data-pos]').forEach((root) => {
        const cart = root.querySelector('[data-cart-lines]');
        const totalEl = root.querySelector('[data-cart-total]');
        const paymentEl = root.querySelector('[data-payment-amount]');
        const emptyEl = root.querySelector('[data-cart-empty]');
        const lines = new Map();

        const money = (value) => Number(value || 0).toFixed(2);

        const render = () => {
            if (!cart) return;

            cart.innerHTML = '';
            let total = 0;
            let index = 0;

            lines.forEach((line, id) => {
                const lineTotal = line.quantity * line.price;
                total += lineTotal;

                const row = document.createElement('div');
                row.className = 'cart-line';
                row.innerHTML = `
                    <input type="hidden" name="items[${index}][product_id]" value="${id}">
                    <input type="hidden" name="items[${index}][unit_price]" value="${line.price}">
                    <span>${line.name}</span>
                    <input name="items[${index}][quantity]" type="number" min="1" step="1" value="${line.quantity}">
                    <strong>${money(lineTotal)}</strong>
                `;
                row.querySelector('input[type="number"]').addEventListener('input', (event) => {
                    line.quantity = Math.max(1, Number(event.target.value || 1));
                    render();
                });
                cart.appendChild(row);
                index += 1;
            });

            if (totalEl) totalEl.textContent = money(total);
            if (paymentEl) paymentEl.value = money(total);
            if (emptyEl) emptyEl.style.display = lines.size ? 'none' : 'grid';
        };

        root.querySelectorAll('[data-product]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-product');
                const existing = lines.get(id);
                if (existing) {
                    existing.quantity += 1;
                } else {
                    lines.set(id, {
                        name: button.getAttribute('data-name'),
                        price: Number(button.getAttribute('data-price') || 0),
                        quantity: 1,
                    });
                }
                render();
            });
        });

        render();
    });

    document.querySelectorAll('[data-accounting-lines]').forEach((form) => {
        const list = form.querySelector('[data-accounting-line-list]');
        const template = form.querySelector('[data-accounting-line-template]');
        const reindex = () => {
            list?.querySelectorAll('[data-accounting-line]').forEach((row, index) => {
                row.querySelectorAll('[name], [data-name]').forEach((field) => {
                    const key = field.dataset.name || field.name.match(/\[([^\]]+)\]$/)?.[1];
                    if (key) field.name = `lines[${index}][${key}]`;
                });
            });
        };
        form.querySelector('[data-add-accounting-line]')?.addEventListener('click', () => {
            list?.appendChild(template.content.cloneNode(true));
            reindex();
        });
        form.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-accounting-line]');
            if (remove && list.querySelectorAll('[data-accounting-line]').length > 2) {
                remove.closest('[data-accounting-line]').remove();
                reindex();
            }
        });
    });

    updateConnectionStatus();
    syncOfflineQueue();
});

window.addEventListener('online', () => {
    updateConnectionStatus();
    syncOfflineQueue();
});
window.addEventListener('offline', updateConnectionStatus);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
