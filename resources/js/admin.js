/**
 * Admin bundle. Bootstrap 5.3 JS for collapse/dropdown/modal/offcanvas
 * plus admin-specific helpers (sidebar toggle on mobile, auto-dismiss
 * alerts, CSRF helper for AJAX).
 */

import * as bootstrap from 'bootstrap';

// Chart.js — registered as a tree-shaken subset so the admin bundle
// only ships the controllers/elements we actually use on the
// dashboard (bar + doughnut).
import {
    Chart,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(
    BarController, BarElement, DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend,
);

// Bootstrap's data-bs-* attributes auto-init via the bundle import; we
// only need explicit JS where we add behaviour beyond the default.

// ---------------------------------------------------------------------------
// Mobile sidebar toggle
// ---------------------------------------------------------------------------
const sidebar = document.getElementById('admin-sidebar');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');

function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', !!open);
    document.body.classList.toggle('admin-sidebar-open', !!open);
}

sidebarToggle?.addEventListener('click', () => {
    setSidebar(!sidebar.classList.contains('is-open'));
});

sidebarBackdrop?.addEventListener('click', () => setSidebar(false));

// ---------------------------------------------------------------------------
// Auto-dismiss Bootstrap alerts 3s after they appear, unless they carry
// the data-no-autohide attribute (e.g. a destructive error).
// ---------------------------------------------------------------------------
document.querySelectorAll('.alert[data-autohide]').forEach((el) => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 3500);
});

// ---------------------------------------------------------------------------
// Confirm-delete modal helper. Add data-confirm="Are you sure?" to a form
// submit button and the form will only submit if the user confirms.
// ---------------------------------------------------------------------------
document.querySelectorAll('form[data-confirm-form]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (form.dataset.confirmed === 'true') return;
        e.preventDefault();
        const message = form.dataset.confirm || 'Are you sure? This cannot be undone.';
        if (window.confirm(message)) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    });
});

// ---------------------------------------------------------------------------
// AJAX helper with CSRF. Pickup can be done with:
//   admin.post('/admin/categories', { name: 'X' })
// ---------------------------------------------------------------------------
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

window.admin = {
    headers: () => ({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    }),

    async request(method, url, body) {
        const res = await fetch(url, {
            method: method.toUpperCase(),
            headers: this.headers(),
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin',
        });
        const isJson = res.headers.get('content-type')?.includes('application/json');
        return { ok: res.ok, status: res.status, data: isJson ? await res.json() : await res.text() };
    },

    get(url) { return this.request('GET', url); },
    post(url, body) { return this.request('POST', url, body); },
    put(url, body) { return this.request('PUT', url, body); },
    patch(url, body) { return this.request('PATCH', url, body); },
    delete(url) { return this.request('DELETE', url); },
};

// Expose Bootstrap on `window.bootstrap` for inline scripts that need it.
window.bootstrap = bootstrap;

// Expose Chart.js for inline scripts that render charts on the
// dashboard. We expose only the class, not the controllers, so each
// inline script remains responsible for picking the config it needs.
window.Chart = Chart;