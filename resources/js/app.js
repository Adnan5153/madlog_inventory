/* ============================================================================
 * Madlog Store — application JavaScript bundle.
 * ============================================================================
 *
 * Single entry point loaded on every page. Owns:
 *   - Bootstrap 5.3 (collapse, dropdown, modal, offcanvas, tooltip, ...)
 *   - Chart.js (registered tree-shaken controllers: bar + doughnut)
 *   - Passkeys helper (`window.Passkeys` for the auth flow)
 *
 * Provides behaviour for:
 *   - Sidebar toggle on desktop (collapse) and mobile (off-canvas)
 *   - Landing-page navbar scroll-spy + collapse-on-tap
 *   - Table density switch (comfortable / compact, localStorage-persisted)
 *   - Global Command Bar (Ctrl/Cmd+K) with keyboard navigation
 *   - Slide-over inspection drawer (Bootstrap Offcanvas)
 *   - Toast container (`window.madlog.toast(...)`)
 *   - Purchase-order line item add/remove
 *   - Demo-account auto-fill on the local login page
 *   - Role/workshop conditional requirement on the local register page
 *   - Dashboard Chart.js rendering
 *   - CSRF + JSON fetch helper (`window.admin.get/post/put/patch/delete`)
 *   - Auto-dismiss alerts (`data-autohide`)
 *   - Confirm-delete helper (`data-confirm-form` on a <form>)
 *
 * Layout:
 *   1. Vendor imports
 *   2. Admin / landing / chart / passkeys setup
 *   3. Navigation behaviour
 *   4. Sidebar behaviour
 *   5. Table density switch
 *   6. Global Command Bar
 *   7. Slide-over inspection drawer
 *   8. Toast container
 *   9. Forms (demo picker, role/workshop toggle, confirm-delete)
 *  10. Inventory UI (PO line items)
 *  11. Notifications (auto-dismiss alerts)
 *  12. Dashboard Chart.js initializers
 *  13. AJAX + CSRF helpers
 *  14. Bootstrap + vendor globals
 * ========================================================================= */


/* ----------------------------------------------------------------------------
 * 1. Vendor imports
 * ---------------------------------------------------------------------------- */

// Vendor CSS — imported from JS so Vite's CSS bundling includes them
// reliably in both dev and production. Importing Bootstrap CSS from
// `app.css` via `@import` interacts badly with `@tailwindcss/vite`'s
// lightningcss preprocessor, which can silently drop non-Tailwind
// `@import` statements after its own directives.
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

// Bootstrap JS bundle (includes Popper). Side-effect import: this auto-wires
// every data-bs-toggle="..." / data-bs-dismiss="..." attribute in the DOM.
// Do NOT remove — even though it looks unused, it's what makes Bootstrap's
// declarative API work.
import * as bootstrap from 'bootstrap';

// Chart.js — register only the controllers + elements used on the admin
// dashboard (bar + doughnut + line) so the bundle stays tree-shaken.
import {
    Chart,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    LineController,
    LineElement,
    PointElement,
    Filler,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(
    BarController, BarElement,
    DoughnutController, ArcElement,
    LineController, LineElement, PointElement, Filler,
    CategoryScale, LinearScale,
    Tooltip, Legend,
);

// Passkeys — exposes `window.Passkeys` for the Livewire/Flux auth flow
// (registration + verification UIs) and dispatches `passkeys:ready` so
// those components can re-check support once it loads.
import { Passkeys } from '@laravel/passkeys';


/* ----------------------------------------------------------------------------
 * 2. Global initialisation
 * ---------------------------------------------------------------------------- */

// `prefers-reduced-motion` — read once; consumers opt-in by checking
// `window.matchMedia('(prefers-reduced-motion: reduce)').matches`.
const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;


// ============================================================================
// 3. Navigation behaviour (landing page)
// ============================================================================
//
// Two small interactions:
//   - Scroll-spy: highlights the navbar link whose target section is in view.
//   - Collapse on tap: hides the mobile navbar after a link inside it is
//     clicked, so the new section isn't covered by the menu.

const navLinks = document.querySelectorAll('[data-nav-link]');
const sections = [...document.querySelectorAll('section[id]')];

if ('IntersectionObserver' in window && navLinks.length && sections.length) {
    const spy = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    navLinks.forEach((link) => {
                        const active = link.getAttribute('data-nav-link') === id;
                        link.classList.toggle('active', active);
                        if (active) {
                            link.setAttribute('aria-current', 'page');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                }
            }
        },
        { rootMargin: '-40% 0px -55% 0px', threshold: 0 }
    );

    sections.forEach((s) => spy.observe(s));
}

document.querySelectorAll('.navbar-collapse .nav-link').forEach((link) => {
    link.addEventListener('click', () => {
        const collapseEl = document.getElementById('mainNav');
        if (collapseEl && collapseEl.classList.contains('show')) {
            const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            collapse.hide();
        }
    });
});


// ============================================================================
// 4. Sidebar behaviour (admin shell)
// ============================================================================
//
// Works on every breakpoint:
//   - Desktop (>= 992px): collapses the sidebar down to icon-only width
//     and persists the state in localStorage so it survives reloads.
//   - Mobile  (<  992px): slides the sidebar on/off-canvas with a backdrop.
//
// The state is split into two independent channels:
//   - `is-open` on the sidebar element + `admin-sidebar-open` on <body>
//     → on/off-canvas (mobile)
//   - `admin-sidebar-collapsed` on <html> + <body>
//     → narrow / icon-only (desktop, pre-paint via inline <head> script)
//
// On page load we re-apply the persisted collapsed state synchronously so
// the first paint is correct (no layout flash).

const sidebar = document.getElementById('admin-sidebar');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
const SIDEBAR_COLLAPSED_KEY = 'madlog.admin.sidebarCollapsed';
const mqDesktop = window.matchMedia('(min-width: 992px)');

const isDesktop = () => mqDesktop.matches;

function readCollapsedState() {
    try {
        return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    } catch {
        return false;
    }
}

function writeCollapsedState(collapsed) {
    try {
        localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
    } catch {
        // localStorage may be unavailable (private mode, etc.) — fail silently.
    }
}

function applyInitialCollapsedState() {
    if (readCollapsedState()) {
        document.body.classList.add('admin-sidebar-collapsed');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    } else {
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    }
}
applyInitialCollapsedState();

function setSidebarOpen(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', !!open);
    document.body.classList.toggle('admin-sidebar-open', !!open);
    // Mirror the open state on the backdrop element so the dim overlay
    // actually shows up on mobile. The element has inline `display:none`
    // by default and the only way to make it visible is to flip style.
    if (sidebarBackdrop) {
        sidebarBackdrop.style.display = open ? 'block' : 'none';
    }
}

function setSidebarCollapsed(collapsed) {
    const root = document.documentElement;
    root.classList.toggle('admin-sidebar-collapsed', !!collapsed);
    document.body.classList.toggle('admin-sidebar-collapsed', !!collapsed);
    writeCollapsedState(!!collapsed);

    if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
}

sidebarToggle?.addEventListener('click', () => {
    if (isDesktop()) {
        // Desktop: toggle the collapsed state.
        setSidebarCollapsed(!document.body.classList.contains('admin-sidebar-collapsed'));
    } else {
        // Mobile: toggle the off-canvas state.
        setSidebarOpen(!sidebar?.classList.contains('is-open'));
    }
});

sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

// Escape closes the off-canvas sidebar on mobile.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (isDesktop()) return;
    if (!sidebar || !sidebar.classList.contains('is-open')) return;
    setSidebarOpen(false);
    // Return focus to the toggle so keyboard users can pick up where they left off.
    sidebarToggle?.focus();
});

// Click anywhere outside the sidebar / toggle to close on mobile.
// Captures clicks at the document level; only acts on mobile + when
// the off-canvas sidebar is currently open. Clicks on the sidebar
// itself or its descendants stop here so nav links can be tapped.
document.addEventListener('click', (e) => {
    if (isDesktop()) return;
    if (!sidebar || !sidebar.classList.contains('is-open')) return;

    const target = e.target;
    if (sidebar.contains(target)) return;
    if (sidebarToggle?.contains(target)) return;

    setSidebarOpen(false);
});

// Close the off-canvas sidebar after navigating via any internal link
// inside it — otherwise the sidebar stays open after the route change
// and obscures the new page. Only matches same-origin <a> links with
// an actual href (skip placeholder `#` and toggle buttons).
sidebar?.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', () => {
        if (!isDesktop() && sidebar.classList.contains('is-open')) {
            // Defer slightly so the navigation has a chance to start.
            setTimeout(() => setSidebarOpen(false), 50);
        }
    });
});

// If the user resizes from mobile -> desktop while the sidebar is "open"
// off-canvas, make sure the body class for the off-canvas state isn't
// lingering (it has no effect on desktop, but better to keep it tidy).
mqDesktop.addEventListener?.('change', (e) => {
    if (e.matches) {
        setSidebarOpen(false);
    }
});


// ============================================================================
// 5. Table density switch
// ============================================================================
//
// Reads / writes `body[data-density]` between `comfortable` and `compact`.
// Persists the choice in `localStorage` so it survives reloads. The
// corresponding CSS lives in `resources/css/app.css` (section 9 — Tables).
//
// Markup expected (topbar):
//
//   <div class="density-toggle" data-density-toggle>
//     <button data-density="comfortable" aria-pressed="true">
//       <i class="bi bi-list"></i> Comfortable
//     </button>
//     <button data-density="compact" aria-pressed="false">
//       <i class="bi bi-list-ol"></i> Compact
//     </button>
//   </div>

const DENSITY_KEY = 'madlog.table.density';
const DEFAULT_DENSITY = 'comfortable';

function readDensity() {
    try {
        const stored = localStorage.getItem(DENSITY_KEY);
        return stored === 'compact' ? 'compact' : DEFAULT_DENSITY;
    } catch {
        return DEFAULT_DENSITY;
    }
}

function writeDensity(value) {
    try {
        localStorage.setItem(DENSITY_KEY, value);
    } catch {
        /* ignore quota / privacy mode */
    }
}

function applyDensity(value) {
    document.body.dataset.density = value;
    document.querySelectorAll('[data-density-toggle] [data-density]').forEach((btn) => {
        const pressed = btn.dataset.density === value;
        btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    });
}

applyDensity(readDensity());

document.querySelectorAll('[data-density-toggle] [data-density]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const next = btn.dataset.density === 'compact' ? 'compact' : 'comfortable';
        applyDensity(next);
        writeDensity(next);
    });
});


// ============================================================================
// 6. Global Command Bar
// ============================================================================
//
// Static navigation palette — every entry maps to a route already
// reachable from the sidebar. Records search is intentionally NOT
// included; future iterations can replace the static `COMMANDS` with
// a fetched list.
//
// Opens with Ctrl+K (Windows / Linux) or Cmd+K (macOS). The dialog is
// rendered lazily on first activation so it doesn't bloat first paint.
//
// Markup injected on first activation:
//
//   <div class="cmd-bar-backdrop" data-cmd-bar-backdrop></div>
//   <div class="cmd-bar" role="dialog" aria-modal="true"
//        aria-label="Command bar" data-cmd-bar>
//     <input type="text" class="cmd-bar__input" ...>
//     <div class="cmd-bar__hint">...</div>
//     <div class="cmd-bar__list" role="listbox"></div>
//     <div class="cmd-bar__footer">...</div>
//   </div>

const COMMANDS = [
    // Overview
    { id: 'dashboard', title: 'Dashboard', hint: 'Overview', icon: 'bi-speedometer2', group: 'Overview',
      href: () => route('admin.dashboard') },

    // Catalog
    { id: 'categories', title: 'Categories', hint: 'Catalog', icon: 'bi-tags', group: 'Catalog',
      href: () => route('admin.categories.index') },
    { id: 'units', title: 'Units of Measure', hint: 'Catalog', icon: 'bi-rulers', group: 'Catalog',
      href: () => route('admin.units.index') },
    { id: 'products', title: 'Products / Parts', hint: 'Catalog', icon: 'bi-box-seam', group: 'Catalog',
      href: () => route('admin.products.index') },
    { id: 'lubricants', title: 'Lubricants', hint: 'Catalog', icon: 'bi-droplet', group: 'Catalog',
      href: () => route('admin.lubricants.index') },

    // Warehousing
    { id: 'warehouses', title: 'Warehouses', hint: 'Warehousing', icon: 'bi-building', group: 'Warehousing',
      href: () => route('admin.warehouses.index') },
    { id: 'bins', title: 'Bin locations', hint: 'Warehousing', icon: 'bi-geo-alt', group: 'Warehousing',
      href: () => route('admin.bin-locations.index') },

    // Procurement
    { id: 'pos', title: 'Purchase orders', hint: 'Procurement', icon: 'bi-receipt', group: 'Procurement',
      href: () => route('admin.purchase-orders.index') },
    { id: 'goods-receipts', title: 'Goods receipts', hint: 'Procurement', icon: 'bi-box-arrow-in-down', group: 'Procurement',
      href: () => route('admin.goods-receipts.index') },
    { id: 'suppliers', title: 'Suppliers', hint: 'Procurement', icon: 'bi-truck', group: 'Procurement',
      href: () => route('admin.suppliers.index') },

    // Inventory Ops
    { id: 'stock-adjust', title: 'Stock adjustments', hint: 'Inventory Ops', icon: 'bi-sliders', group: 'Inventory Ops',
      href: () => route('admin.stock-adjustments.index') },
    { id: 'stock-transfer', title: 'Stock transfers', hint: 'Inventory Ops', icon: 'bi-arrow-left-right', group: 'Inventory Ops',
      href: () => route('admin.stock-transfers.index') },
    { id: 'lubricant-adjustments', title: 'Lubricant adjustments', hint: 'Inventory Ops', icon: 'bi-droplet', group: 'Inventory Ops',
      href: () => route('admin.lubricant-stock-adjustments.index') },

    // Access
    { id: 'users', title: 'Users', hint: 'Access', icon: 'bi-people', group: 'Access',
      href: () => route('admin.users.index') },
    { id: 'roles', title: 'Roles', hint: 'Access', icon: 'bi-shield-lock', group: 'Access',
      href: () => route('admin.roles.index') },
    { id: 'permissions', title: 'Permissions', hint: 'Access', icon: 'bi-key', group: 'Access',
      href: () => route('admin.permissions.index') },

    // Reports
    { id: 'valuation', title: 'Inventory valuation', hint: 'Reports', icon: 'bi-cash-coin', group: 'Reports',
      href: () => route('admin.reports.valuation') },
    { id: 'low-stock', title: 'Low stock', hint: 'Reports', icon: 'bi-exclamation-triangle', group: 'Reports',
      href: () => route('admin.reports.low-stock') },
    { id: 'movements', title: 'Movement history', hint: 'Reports', icon: 'bi-arrow-left-right', group: 'Reports',
      href: () => route('admin.reports.movements') },
    { id: 'top-consumed', title: 'Top consumed', hint: 'Reports', icon: 'bi-bar-chart', group: 'Reports',
      href: () => route('admin.reports.top-consumed') },

    // Operations
    { id: 'departments', title: 'Departments', hint: 'Operations', icon: 'bi-diagram-3', group: 'Operations',
      href: () => route('admin.departments.index') },
    { id: 'equipment', title: 'Equipment', hint: 'Operations', icon: 'bi-tools', group: 'Operations',
      href: () => route('admin.equipment.index') },

    // Tooling
    { id: 'tools-dashboard', title: 'Tools dashboard', hint: 'Tooling', icon: 'bi-wrench-adjustable', group: 'Tooling',
      href: () => route('admin.tools.dashboard') },
    { id: 'tools', title: 'Tools', hint: 'Tooling', icon: 'bi-tools', group: 'Tooling',
      href: () => route('admin.tools.index') },
    { id: 'tool-categories', title: 'Tool categories', hint: 'Tooling', icon: 'bi-tags', group: 'Tooling',
      href: () => route('admin.tool-categories.index') },

    // System
    { id: 'settings', title: 'Settings', hint: 'System', icon: 'bi-sliders', group: 'System',
      href: () => route('admin.settings.edit') },
    { id: 'audit', title: 'Audit logs', hint: 'System', icon: 'bi-clock-history', group: 'System',
      href: () => route('admin.audit-logs.index') },

    // Cross-cutting
    { id: 'toggle-density', title: 'Toggle table density', hint: 'Quick action', icon: 'bi-list-ol', group: 'Quick actions',
      action: () => {
          const next = readDensity() === 'compact' ? 'comfortable' : 'compact';
          applyDensity(next);
          writeDensity(next);
      } },
    { id: 'docs', title: 'Documentation', hint: 'Quick action', icon: 'bi-book', group: 'Quick actions',
      href: () => 'https://laravel.com/docs' },
];

// Resolve a `route()`-style name through Laravel's global `route()` if
// present; otherwise fall back to the raw href so this doesn't break
// when loaded outside the admin shell.
//
// The admin layout publishes a `window.madlogRoutes` map
// (`{ name: '/path' }`) so we can resolve without needing Ziggy.
function resolveHref(entry) {
    if (typeof entry.href === 'string') return entry.href;
    try {
        return entry.href();
    } catch {
        return '#';
    }
}

// `route(name)` polyfill. Reads the embedded map first; falls back to
// Laravel's global `route()` if available; returns `'#'` as last resort.
window.route = function route(name) {
    const map = window.madlogRoutes || {};
    if (map[name]) return map[name];
    if (typeof window._laravelRoute === 'function') {
        try { return window._laravelRoute(name); } catch { /* fall through */ }
    }
    return '#';
};

let cmdBar = null; // { root, backdrop, input, list }
let cmdLastFocused = null;

function buildCmdBar() {
    if (cmdBar) return cmdBar;

    const backdrop = document.createElement('div');
    backdrop.className = 'cmd-bar-backdrop';
    backdrop.dataset.cmdBarBackdrop = '';
    backdrop.hidden = true;

    const root = document.createElement('div');
    root.className = 'cmd-bar';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Command bar');
    root.dataset.cmdBar = '';
    root.hidden = true;
    root.innerHTML = `
        <input type="text" class="cmd-bar__input" placeholder="Search or jump to..." aria-label="Command" autocomplete="off" spellcheck="false">
        <div class="cmd-bar__hint small">
            <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span>
            <span><kbd>&crarr;</kbd> select</span>
            <span><kbd>esc</kbd> close</span>
        </div>
        <div class="cmd-bar__list" role="listbox" aria-label="Commands"></div>
        <div class="cmd-bar__footer">
            <span>Madlog command bar</span>
            <span>${COMMANDS.length} commands</span>
        </div>
    `;

    document.body.appendChild(backdrop);
    document.body.appendChild(root);

    const input = root.querySelector('.cmd-bar__input');
    const list = root.querySelector('.cmd-bar__list');

    cmdBar = { root, backdrop, input, list, selected: -1, filtered: COMMANDS.slice() };

    // Filter & render as user types.
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        cmdBar.filtered = q
            ? COMMANDS.filter((c) =>
                  c.title.toLowerCase().includes(q) ||
                  c.hint.toLowerCase().includes(q) ||
                  c.group.toLowerCase().includes(q))
            : COMMANDS.slice();
        cmdBar.selected = 0;
        renderCmdList();
    });

    // Keyboard nav.
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveCmdSelection(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveCmdSelection(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); activateCurrent(); }
        else if (e.key === 'Escape') { e.preventDefault(); closeCmdBar(); }
        else if (e.key === 'Tab') {
            // Trap focus inside the dialog while open.
            const focusables = root.querySelectorAll('input, [tabindex]');
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Click on an item.
    list.addEventListener('click', (e) => {
        const item = e.target.closest('.cmd-bar__item');
        if (!item) return;
        const id = item.dataset.cmdId;
        const entry = COMMANDS.find((c) => c.id === id);
        if (!entry) return;
        if (entry.action) entry.action();
        else window.location.href = resolveHref(entry);
        closeCmdBar();
    });

    // Mouse hover selects.
    list.addEventListener('mousemove', (e) => {
        const item = e.target.closest('.cmd-bar__item');
        if (!item) return;
        const idx = cmdBar.filtered.findIndex((c) => c.id === item.dataset.cmdId);
        if (idx >= 0 && idx !== cmdBar.selected) {
            cmdBar.selected = idx;
            renderCmdList();
        }
    });

    backdrop.addEventListener('click', closeCmdBar);

    return cmdBar;
}

function renderCmdList() {
    if (!cmdBar) return;
    const { list, filtered, selected } = cmdBar;
    list.innerHTML = '';

    if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'cmd-bar__empty';
        empty.textContent = 'No commands match your search.';
        list.appendChild(empty);
        return;
    }

    // Group by group, render group title once.
    let lastGroup = null;
    filtered.forEach((c, i) => {
        if (c.group !== lastGroup) {
            const h = document.createElement('div');
            h.className = 'cmd-bar__group-title';
            h.textContent = c.group;
            list.appendChild(h);
            lastGroup = c.group;
        }

        const item = document.createElement('a');
        item.className = 'cmd-bar__item';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', i === selected ? 'true' : 'false');
        item.dataset.cmdId = c.id;
        item.href = c.action ? '#' : resolveHref(c);
        item.innerHTML = `
            <i class="bi ${c.icon}" aria-hidden="true"></i>
            <span class="cmd-bar__item-title">${c.title}</span>
            <span class="cmd-bar__item-hint">${c.hint}</span>
        `;
        list.appendChild(item);
    });

    // Scroll the selected item into view.
    const selectedEl = list.querySelector('[aria-selected="true"]');
    if (selectedEl) selectedEl.scrollIntoView({ block: 'nearest' });
}

function moveCmdSelection(delta) {
    if (!cmdBar || cmdBar.filtered.length === 0) return;
    cmdBar.selected = (cmdBar.selected + delta + cmdBar.filtered.length) % cmdBar.filtered.length;
    renderCmdList();
}

function activateCurrent() {
    if (!cmdBar || cmdBar.selected < 0) return;
    const entry = cmdBar.filtered[cmdBar.selected];
    if (!entry) return;
    if (entry.action) entry.action();
    else window.location.href = resolveHref(entry);
    closeCmdBar();
}

function openCmdBar() {
    buildCmdBar();
    cmdLastFocused = document.activeElement;
    cmdBar.root.hidden = false;
    cmdBar.backdrop.hidden = false;
    cmdBar.input.value = '';
    cmdBar.filtered = COMMANDS.slice();
    cmdBar.selected = 0;
    renderCmdList();
    // Defer focus to next frame so the input is visible.
    requestAnimationFrame(() => cmdBar.input.focus());
}

function closeCmdBar() {
    if (!cmdBar) return;
    cmdBar.root.hidden = true;
    cmdBar.backdrop.hidden = true;
    if (cmdLastFocused && typeof cmdLastFocused.focus === 'function') {
        cmdLastFocused.focus();
    }
}

// Global hotkey + button triggers.
document.addEventListener('keydown', (e) => {
    const isMac = navigator.userAgent.includes('Mac');
    const meta = isMac ? e.metaKey : e.ctrlKey;
    if (meta && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (cmdBar && !cmdBar.root.hidden) {
            closeCmdBar();
        } else {
            openCmdBar();
        }
    }
});

document.querySelectorAll('[data-cmd-bar-open]').forEach((btn) => {
    btn.addEventListener('click', openCmdBar);
});


// ============================================================================
// 7. Slide-over inspection drawer
// ============================================================================
//
// `[data-drawer-open]` triggers Bootstrap Offcanvas. The button may
// carry:
//   - `data-drawer-url`   : URL to fetch the drawer body as JSON
//   - `data-drawer-title` : Title to render in the header
//   - `data-drawer-target`: An existing element id to clone from the page
// Fetches JSON or falls back to a plain `<div>` body.

function buildDrawer(title, bodyHtml) {
    const id = `madlog-drawer-${Date.now()}`;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="offcanvas offcanvas-end madlog-drawer" tabindex="-1" id="${id}" aria-labelledby="${id}-title">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="${id}-title">${title || 'Inspect'}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">${bodyHtml || '<div class="madlog-drawer__section"><div class="madlog-drawer__value text-muted">No content.</div></div>'}</div>
        </div>
    `;
    document.body.appendChild(wrapper.firstElementChild);
    const el = document.getElementById(id);
    const instance = bootstrap.Offcanvas.getOrCreateInstance(el);
    instance.show();
    el.addEventListener('hidden.bs.offcanvas', () => el.remove());
    return instance;
}

async function openDrawerFromButton(btn) {
    const url = btn.dataset.drawerUrl;
    const title = btn.dataset.drawerTitle || btn.textContent.trim() || 'Inspect';

    if (url) {
        try {
            const res = await window.admin.get(url);
            const html = typeof res.data === 'string' ? res.data
                : (res.data?.html || `<pre class="madlog-drawer__section small">${JSON.stringify(res.data, null, 2)}</pre>`);
            buildDrawer(title, html);
        } catch (e) {
            // Fallback: navigate.
            window.location.href = url;
        }
        return;
    }

    const targetId = btn.dataset.drawerTarget;
    if (targetId) {
        const src = document.getElementById(targetId);
        if (src) buildDrawer(title, src.innerHTML);
    }
}

document.querySelectorAll('[data-drawer-open]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        openDrawerFromButton(btn);
    });
});


// ============================================================================
// 8. Toast container
// ============================================================================
//
// `window.madlog.toast(message, { type, icon, duration })` renders a
// bottom-right toast using the existing alert classes + auto-dismiss.
// Stack is created lazily.

function ensureToastStack() {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        stack.setAttribute('role', 'region');
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }
    return stack;
}

const TOAST_ICONS = {
    success: 'bi-check-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    danger:  'bi-x-octagon-fill',
    error:   'bi-x-octagon-fill',
    info:    'bi-info-circle-fill',
    primary: 'bi-stars',
};

function toast(message, opts = {}) {
    const stack = ensureToastStack();
    const type = opts.type || 'primary';
    const duration = opts.duration ?? 4000;
    const icon = opts.icon || TOAST_ICONS[type] || 'bi-bell-fill';

    const item = document.createElement('div');
    item.className = `toast-item is-${type}`;
    item.setAttribute('role', 'status');
    item.innerHTML = `
        <i class="bi ${icon}" aria-hidden="true"></i>
        <div class="flex-grow-1">${message}</div>
        <button type="button" class="btn-ghost btn-inspect p-0 ms-2" aria-label="Dismiss">
            <i class="bi bi-x" aria-hidden="true"></i>
        </button>
    `;
    stack.appendChild(item);

    const dismiss = () => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(8px)';
        item.style.transition = 'opacity .15s ease, transform .15s ease';
        setTimeout(() => item.remove(), 200);
    };

    item.querySelector('button')?.addEventListener('click', dismiss);

    if (duration > 0) {
        setTimeout(dismiss, duration);
    }
}

window.madlog = window.madlog || {};
window.madlog.toast = toast;


// ============================================================================
// 9. Forms
// ============================================================================
//
// 5a. Demo-account picker on the local login page (`#demo-account`).
//     Auto-fills the email + password fields and shows a hint with the role
//     and workshop label of the picked account.
//
// 5b. Role/workshop conditional requirement on the local register page
//     (`#role` / `#workshop_id`). When role=staff the workshop becomes
//     required; when role is empty (public visitor) it's disabled.
//
// 5c. Confirm-delete on any `<form data-confirm-form data-confirm="...">`.
//     Intercepts submit and shows a confirm() prompt before proceeding.

// 5a. Demo account picker ----------------------------------------------------
(function initDemoAccountPicker() {
    const picker = document.getElementById('demo-account');
    if (!picker) return;

    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const hint = document.getElementById('demo-hint');
    const hintRole = document.getElementById('demo-hint-role');
    const hintWorkshop = document.getElementById('demo-hint-workshop');
    if (!emailInput || !passwordInput) return;

    function applySelection() {
        const option = picker.options[picker.selectedIndex];
        if (!option || !option.value) {
            if (hint) hint.classList.add('hidden');
            return;
        }

        emailInput.value = option.value;
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));

        passwordInput.value = option.dataset.password || '';
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));

        if (hint && hintRole) {
            const role = option.dataset.role || '';
            const isGlobal = option.dataset.global === '1';
            const workshop = option.dataset.workshop || '';
            hintRole.textContent = role === 'admin'
                ? (isGlobal ? 'Global admin' : 'Workshop admin')
                : 'Staff';
            hintWorkshop.textContent = workshop ? ' — ' + workshop : '';
            hint.classList.remove('hidden');
        }
    }

    picker.addEventListener('change', applySelection);
})();

// 5b. Role/workshop toggle (register page) -----------------------------------
(function initRoleWorkshopToggle() {
    const role = document.getElementById('role');
    const workshop = document.getElementById('workshop_id');
    if (!role || !workshop) return;

    function sync() {
        const needsWorkshop = role.value === 'staff';
        workshop.required = needsWorkshop;
        workshop.disabled = role.value === '';

        // Visual cue for the required state (uses Flux/Tailwind utilities).
        workshop.classList.toggle('ring-1', needsWorkshop);
        workshop.classList.toggle('ring-amber-400', needsWorkshop);
    }

    role.addEventListener('change', sync);
    sync();
})();

// 5c. Confirm-delete ---------------------------------------------------------
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


// ============================================================================
// 6. Inventory UI — purchase-order line items
// ============================================================================
//
// Wires up the `#po-add` / `#po-remove` controls on the PO form. New rows
// are appended with the next index so the server-side validation sees a
// contiguous `items[i][...]` array.

(function initPurchaseOrderLines() {
    const wrap = document.getElementById('po-lines');
    const addBtn = document.getElementById('po-add');
    if (!wrap || !addBtn) return;

    addBtn.addEventListener('click', () => {
        const idx = wrap.querySelectorAll('.po-line').length;
        const tmpl = document.createElement('div');
        tmpl.className = 'row g-2 align-items-end po-line mb-2';
        tmpl.innerHTML = `
            <div class="col-md-5"><label class="form-label small">Part</label><input type="number" name="items[${idx}][part_id]" class="form-control" placeholder="Part ID" required></div>
            <div class="col-md-3"><label class="form-label small">Quantity</label><input type="number" step="0.01" name="items[${idx}][quantity_ordered]" class="form-control" value="1" min="0.01" required></div>
            <div class="col-md-3"><label class="form-label small">Unit cost</label><input type="number" step="0.01" name="items[${idx}][unit_cost]" class="form-control" value="0.00" min="0" required></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm po-remove" title="Remove"><i class="bi bi-trash"></i></button></div>
        `;
        wrap.appendChild(tmpl);
    });

    wrap.addEventListener('click', (e) => {
        const btn = e.target.closest('.po-remove');
        if (!btn) return;
        const line = btn.closest('.po-line');
        if (line && wrap.querySelectorAll('.po-line').length > 1) {
            line.remove();
        }
    });
})();


// ============================================================================
// 7. Notifications — auto-dismiss Bootstrap alerts
// ============================================================================
//
// Any `.alert[data-autohide]` is dismissed 3.5s after page load. Use
// `data-no-autohide` on destructive errors to opt out.

document.querySelectorAll('.alert[data-autohide]').forEach((el) => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 3500);
});


// ============================================================================
// 8. Dashboard Chart.js initializers
// ============================================================================
//
// Bar chart (`#chart-top-consumed`) — top-N most-consumed parts over the last
// 30 days. Reads its data from `<canvas data-chart="top-consumed">` so the
// page only has to provide JSON via `window.__dashboardCharts` set in the
// layout (kept simple here: reads from a known global if present).
//
// Doughnut chart (`#chart-inventory-by-category`) — inventory value grouped
// by category. Same opt-in mechanism.

function renderTopConsumed() {
    const canvas = document.getElementById('chart-top-consumed');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.topConsumed;
    if (!data || !data.labels?.length) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Units consumed',
                data: data.values,
                backgroundColor: getComputedStyle(document.documentElement)
                    .getPropertyValue('--madlog-primary').trim() || '#4f46e5',
                borderRadius: 4,
                maxBarThickness: 32,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.parsed.x} units`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                },
            },
        },
    });
}

function renderInventoryByCategory() {
    const canvas = document.getElementById('chart-inventory-by-category');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.inventoryByCat;
    if (!data || !data.labels?.length) return;

    // Pull palette tokens from CSS so charts follow the design language.
    const root = getComputedStyle(document.documentElement);
    const palette = [
        root.getPropertyValue('--madlog-primary').trim() || '#4f46e5',
        root.getPropertyValue('--chart-2').trim() || '#10b981',
        root.getPropertyValue('--chart-3').trim() || '#f59e0b',
        root.getPropertyValue('--chart-4').trim() || '#ef4444',
        root.getPropertyValue('--chart-5').trim() || '#8b5cf6',
        root.getPropertyValue('--chart-6').trim() || '#06b6d4',
        root.getPropertyValue('--chart-7').trim() || '#f97316',
        root.getPropertyValue('--chart-8').trim() || '#84cc16',
    ];
    const colors = data.labels.map((_, i) => palette[i % palette.length]);
    const total = data.total ?? 0;

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const value = Number(ctx.parsed) || 0;
                            const pct = total > 0
                                ? ((value / total) * 100).toFixed(1)
                                : '0.0';
                            return `${ctx.label}: ${value.toFixed(2)} (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '55%',
        },
    });
}

// Bar chart (`#chart-monthly-movements`) — stock-in vs stock-out totals
// per month over the last 12 months. Two datasets sharing the same label
// axis so the bars group by month.
function renderMonthlyMovements() {
    const canvas = document.getElementById('chart-monthly-movements');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.monthlyMovements;
    if (!data || !data.labels?.length) return;

    const root = getComputedStyle(document.documentElement);
    const stockInColor = root.getPropertyValue('--chart-2').trim() || '#10b981';
    const stockOutColor = root.getPropertyValue('--chart-4').trim() || '#ef4444';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Stock-In',
                    data: data.stockIn,
                    backgroundColor: stockInColor,
                    borderRadius: 4,
                    maxBarThickness: 24,
                },
                {
                    label: 'Stock-Out',
                    data: data.stockOut,
                    backgroundColor: stockOutColor,
                    borderRadius: 4,
                    maxBarThickness: 24,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} units`,
                    },
                },
            },
            scales: {
                x: { ticks: { autoSkip: true, maxRotation: 0 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
}

// Line chart (`#chart-movement-trend`) — same monthly dataset as the
// bar chart, rendered as a smooth line so operators can read the
// trajectory at a glance.
function renderMovementTrend() {
    const canvas = document.getElementById('chart-movement-trend');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.movementTrend;
    if (!data || !data.labels?.length) return;

    const root = getComputedStyle(document.documentElement);
    const stockInColor = root.getPropertyValue('--chart-2').trim() || '#10b981';
    const stockOutColor = root.getPropertyValue('--chart-4').trim() || '#ef4444';

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Stock-In',
                    data: data.stockIn,
                    borderColor: stockInColor,
                    backgroundColor: stockInColor,
                    tension: 0.3,
                    fill: false,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                },
                {
                    label: 'Stock-Out',
                    data: data.stockOut,
                    borderColor: stockOutColor,
                    backgroundColor: stockOutColor,
                    tension: 0.3,
                    fill: false,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} units`,
                    },
                },
            },
            scales: {
                x: { ticks: { autoSkip: true, maxRotation: 0 } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
}

// Doughnut chart (`#chart-quantity-by-category`) — current on-hand
// quantity (not value) grouped by PartCategory. Reuses the existing
// palette cycle so slices read consistently across the page.
function renderQuantityByCategory() {
    const canvas = document.getElementById('chart-quantity-by-category');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.quantityByCategory;
    if (!data || !data.labels?.length) return;

    const root = getComputedStyle(document.documentElement);
    const palette = [
        root.getPropertyValue('--madlog-primary').trim() || '#4f46e5',
        root.getPropertyValue('--chart-2').trim() || '#10b981',
        root.getPropertyValue('--chart-3').trim() || '#f59e0b',
        root.getPropertyValue('--chart-4').trim() || '#ef4444',
        root.getPropertyValue('--chart-5').trim() || '#8b5cf6',
        root.getPropertyValue('--chart-6').trim() || '#06b6d4',
        root.getPropertyValue('--chart-7').trim() || '#f97316',
        root.getPropertyValue('--chart-8').trim() || '#84cc16',
    ];
    const colors = data.labels.map((_, i) => palette[i % palette.length]);
    const total = data.values.reduce((acc, n) => acc + Number(n || 0), 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const value = Number(ctx.parsed) || 0;
                            const pct = total > 0
                                ? ((value / total) * 100).toFixed(1)
                                : '0.0';
                            return `${ctx.label}: ${value.toFixed(2)} units (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '55%',
        },
    });
}

// Horizontal bar chart (`#chart-stock-value-by-category`) — top 10
// buckets ranked by monetary value, summed across Parts/Batteries/
// Lubricants so the operator sees where money is tied up.
function renderStockValueByCategory() {
    const canvas = document.getElementById('chart-stock-value-by-category');
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.stockValueByCat;
    if (!data || !data.labels?.length) return;

    const root = getComputedStyle(document.documentElement);
    const primary = root.getPropertyValue('--madlog-primary').trim() || '#4f46e5';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Value',
                data: data.values,
                backgroundColor: primary,
                borderRadius: 4,
                maxBarThickness: 18,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `$${Number(ctx.parsed.x).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: (v) => `$${Number(v).toLocaleString()}`,
                    },
                },
            },
        },
    });
}

// Generic doughnut initialiser for the standalone subsystem charts
// (Batteries, Lubricants, Tools). The payload is keyed by `datasetKey`
// in `window.__dashboardCharts` and may be null when the subsystem is
// not in use — we simply skip rendering in that case.
function renderSubsystemDoughnut(canvasId, datasetKey) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !window.Chart) return;
    const data = window.__dashboardCharts?.[datasetKey];
    if (!data || !data.labels?.length) return;

    const root = getComputedStyle(document.documentElement);
    const palette = [
        root.getPropertyValue('--madlog-primary').trim() || '#4f46e5',
        root.getPropertyValue('--chart-2').trim() || '#10b981',
        root.getPropertyValue('--chart-3').trim() || '#f59e0b',
        root.getPropertyValue('--chart-4').trim() || '#ef4444',
        root.getPropertyValue('--chart-5').trim() || '#8b5cf6',
        root.getPropertyValue('--chart-6').trim() || '#06b6d4',
        root.getPropertyValue('--chart-7').trim() || '#f97316',
        root.getPropertyValue('--chart-8').trim() || '#84cc16',
    ];
    const colors = data.labels.map((_, i) => palette[i % palette.length]);
    const total = data.values.reduce((acc, n) => acc + Number(n || 0), 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const value = Number(ctx.parsed) || 0;
                            const pct = total > 0
                                ? ((value / total) * 100).toFixed(1)
                                : '0.0';
                            return `${ctx.label}: ${value.toFixed(2)} (${pct}%)`;
                        },
                    },
                },
            },
            cutout: '55%',
        },
    });
}

// Defer to next paint so the container has its final size before Chart.js
// measures it (prevents the "tall canvas" flash on first render).
if (!prefersReducedMotion) {
    requestAnimationFrame(() => {
        renderTopConsumed();
        renderInventoryByCategory();
        renderMonthlyMovements();
        renderMovementTrend();
        renderQuantityByCategory();
        renderStockValueByCategory();
        renderSubsystemDoughnut('chart-batteries', 'batteries');
        renderSubsystemDoughnut('chart-lubricants', 'lubricants');
        renderSubsystemDoughnut('chart-tools', 'tools');
    });
}


// ============================================================================
// 9. AJAX + CSRF helpers
// ============================================================================
//
// `window.admin` exposes a small JSON-aware fetch helper that:
//   - sends Accept + Content-Type JSON
//   - attaches X-CSRF-TOKEN from <meta name="csrf-token">
//   - marks the request as XHR so Laravel returns JSON for 422/500
//
// Usage:
//   await admin.post('/admin/categories', { name: 'X' });
//   await admin.delete('/admin/categories/1');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
window.csrfToken = csrfToken;

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
        return {
            ok: res.ok,
            status: res.status,
            data: isJson ? await res.json() : await res.text(),
        };
    },

    get(url)    { return this.request('GET',    url); },
    post(url, b){ return this.request('POST',   url, b); },
    put(url, b) { return this.request('PUT',    url, b); },
    patch(url,b){ return this.request('PATCH',  url, b); },
    delete(url) { return this.request('DELETE', url); },
};


// ============================================================================
// 9b. Live search (generic, per-table)
// ============================================================================
//
// Generic helper that wires any list-page filter form to a JSON endpoint
// and replaces the table body + paginator in place — no page reload. Used
// by every admin list page that has a filter bar.
//
// Contract: the JSON endpoint must respond with
//   { rows: ["<tr>…</tr>", …],
//     pagination: "<nav>…</nav>",
//     total: N, page: P, last_page: L, per_page: K, word: "product" }
// where `rows` are pre-rendered HTML strings (the row template) and
// `pagination` is the rendered Bootstrap 5 paginator that the JS swaps
// into `[data-live-search-pagination]`. New filter inputs always reset
// `page` to 1; pagination link clicks use pushState so the back button
// walks the paginated history. Falls back to a regular form submit when
// fetch fails.

(function initLiveSearchTables() {
    function debounce(fn, wait) {
        let t = null;
        return function debounced(...args) {
            if (t) clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // Names of every query parameter this page manages. Used to strip
    // stale params from the URL bar when a filter changes.
    const FILTER_PARAM_KEYS = (form) => {
        const keys = new Set(['page']);
        const searchInput = form.querySelector('[data-live-search-input]');
        if (searchInput && searchInput.name) keys.add(searchInput.name);
        form.querySelectorAll('[data-live-search-control]').forEach((el) => {
            if (el.name) keys.add(el.name);
        });
        return keys;
    };

    function buildQuery(form, searchInput, controls) {
        const params = new URLSearchParams();
        if (searchInput && searchInput.value) params.set('q', searchInput.value.trim());
        controls.forEach((el) => {
            if (!el.name) return;
            const v = (el.value ?? '').trim();
            if (v !== '') params.set(el.name, v);
        });
        return params;
    }

    function syncUrl(params, mode) {
        const url = new URL(window.location.href);
        // Drop every param we manage; then re-set only the ones with
        // non-empty values. Stale `page=` never lingers in the URL bar.
        params.forEach((_, k) => url.searchParams.delete(k));
        params.forEach((v, k) => url.searchParams.set(k, v));
        if (mode === 'push') {
            window.history.pushState({ liveSearch: true }, '', url.toString());
        } else {
            window.history.replaceState({ liveSearch: true }, '', url.toString());
        }
    }

    function wireConfirmForms(root) {
        if (!root.querySelector('form[data-confirm-form]')) return;
        root.querySelectorAll('form[data-confirm-form]').forEach((formEl) => {
            if (formEl.dataset.confirmWired === 'true') return;
            formEl.dataset.confirmWired = 'true';
            formEl.addEventListener('submit', (e) => {
                if (formEl.dataset.confirmed === 'true') return;
                e.preventDefault();
                const message = formEl.dataset.confirm || 'Are you sure? This cannot be undone.';
                if (window.confirm(message)) {
                    formEl.dataset.confirmed = 'true';
                    formEl.submit();
                }
            });
        });
    }

    function initOne(form) {
        const searchUrl = form.dataset.searchUrl;
        if (!searchUrl) return;

        const searchInput = form.querySelector('[data-live-search-input]');
        const controls = form.querySelectorAll('[data-live-search-control]');

        // The target tbody lives OUTSIDE the form (it's part of the page's
        // table). Look it up by id from data-live-search-target, or fall
        // back to the page's only tbody.
        const targetId = form.dataset.liveSearchTarget;
        const tbody = targetId
            ? document.getElementById(targetId)
            : document.querySelector('table tbody');
        if (!tbody) return;

        const statusEl = document.querySelector('[data-live-search-status]');
        const statusLabel = document.querySelector('[data-live-search-status-label]');
        const countEl = document.querySelector('[data-live-search-count]');
        const paginationEl = document.querySelector('[data-live-search-pagination]');
        const managedKeys = FILTER_PARAM_KEYS(form);

        function setStatus(message) {
            if (!statusEl) return;
            if (message === null) {
                statusEl.hidden = true;
                return;
            }
            statusEl.hidden = false;
            if (statusLabel) statusLabel.textContent = message;
        }

        function renderEmpty() {
            const empty = `<tr><td colspan="100">
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center">
                        <i class="bi bi-search display-6 text-muted mb-2" aria-hidden="true"></i>
                        <h2 class="h6 mb-1">No results</h2>
                        <p class="text-muted small mb-0">Try a different search or clear the filters.</p>
                    </div>
                </td></tr>`;
            tbody.innerHTML = empty;
        }

        function applyPagination(data) {
            if (!paginationEl) return;
            // The endpoint returns a fully rendered Bootstrap 5 paginator
            // HTML string. We swap it into the wrapper. When the dataset
            // fits on a single page, the endpoint omits the HTML.
            if (typeof data.pagination === 'string' && data.pagination !== '') {
                paginationEl.innerHTML = data.pagination;
                paginationEl.style.display = '';
            } else {
                paginationEl.innerHTML = '';
                paginationEl.style.display = 'none';
            }
        }

        function applyCount(data) {
            if (!countEl) return;
            const n = Number(data.total) || 0;
            const word = (data.word || 'item') + (n === 1 ? '' : 's');
            const lastPage = Number(data.last_page) || 1;
            const currentPage = Number(data.page) || 1;
            if (n === 0) {
                countEl.textContent = `0 ${word}`;
            } else if (lastPage > 1) {
                countEl.textContent = `${n} ${word} · page ${currentPage} of ${lastPage}`;
            } else {
                countEl.textContent = `${n} ${word}`;
            }
        }

        let inflight = null;
        let seq = 0;

        // Pagination clicks pass `pageParams` so the request carries the
        // desired `page=`. Everything else is treated as a "filter change"
        // and the response lands on page 1.
        async function fetchAndRender({ pageParams, urlMode = 'replace', resetPage = !pageParams } = {}) {
            const params = buildQuery(form, searchInput, controls);

            if (pageParams) {
                pageParams.forEach((v, k) => params.set(k, v));
            } else if (resetPage) {
                params.delete('page');
            }

            const mySeq = ++seq;
            setStatus('Searching…');

            if (inflight && inflight.abort) inflight.abort();
            const controller = new AbortController();
            inflight = controller;

            try {
                const res = await fetch(searchUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                if (mySeq !== seq) return; // a newer request is in flight

                if (Array.isArray(data.rows) && data.rows.length > 0) {
                    tbody.innerHTML = data.rows.join('');
                } else {
                    renderEmpty();
                }

                applyCount(data);
                applyPagination(data);

                syncUrl(params, urlMode === 'push' ? 'push' : 'replace');

                // Re-wire any confirm-delete forms that were just injected.
                wireConfirmForms(tbody);
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.error('Live search failed:', err);
                form.submit();
                return;
            } finally {
                if (mySeq === seq) setStatus(null);
            }
        }

        const debouncedFetch = debounce(() => fetchAndRender(), 200);

        if (searchInput) {
            searchInput.addEventListener('input', () => { debouncedFetch(); });
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); fetchAndRender(); }
            });
        }

        controls.forEach((el) => {
            const tag = el.tagName.toLowerCase();
            if (tag === 'select' || el.type === 'checkbox' || el.type === 'radio') {
                el.addEventListener('change', () => { fetchAndRender(); });
            } else if (tag === 'input' || tag === 'textarea') {
                el.addEventListener('input', () => { debouncedFetch(); });
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); fetchAndRender(); }
                });
            }
        });

        // Pagination interception: clicks on .page-link inside the
        // swapped-in paginator must NOT do a full page navigation —
        // instead, fetch the new page and swap both tbody + paginator.
        // Use event delegation so dynamically injected links also work.
        if (paginationEl) {
            paginationEl.addEventListener('click', (e) => {
                const link = e.target.closest('a.page-link');
                if (!link || !link.href) return;
                e.preventDefault();

                const newPage = new URL(link.href).searchParams.get('page');
                const pageParams = new URLSearchParams();
                if (newPage) pageParams.set('page', newPage);

                fetchAndRender({ pageParams, urlMode: 'push', resetPage: false });
            });
        }

        // Clear button: reset visible inputs, drop `page`, re-fetch
        // from page 1 without a full page reload.
        const clearBtn = document.querySelector('[data-live-search-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (searchInput) searchInput.value = '';
                controls.forEach((el) => { el.value = ''; });
                // Strip any ?page= from the URL bar immediately so the user
                // sees a clean state even before the request resolves.
                const cleaned = new URL(window.location.href);
                managedKeys.forEach((k) => cleaned.searchParams.delete(k));
                window.history.replaceState({ liveSearch: true }, '', cleaned.toString());
                fetchAndRender();
            });
        }

        // Browser back/forward: re-fetch whatever the URL bar says so the
        // table stays in sync with history navigation.
        window.addEventListener('popstate', (e) => {
            if (!e.state || !e.state.liveSearch) return;
            const u = new URL(window.location.href);
            if (searchInput) {
                searchInput.value = u.searchParams.get(searchInput.name || 'q') ?? '';
            }
            controls.forEach((el) => {
                if (!el.name) return;
                el.value = u.searchParams.get(el.name) ?? '';
            });
            // Honour the URL's `page=` exactly — popstate is restoring
            // prior state, not starting a new filter set.
            const page = u.searchParams.get('page');
            const pageParams = page ? new URLSearchParams([['page', page]]) : undefined;
            fetchAndRender({ pageParams, resetPage: false });
        });

        // If the URL already has filter params (deep link / refresh), run
        // an initial fetch so the table matches the URL bar exactly.
        if (window.location.search) {
            const page = new URL(window.location.href).searchParams.get('page');
            const pageParams = page ? new URLSearchParams([['page', page]]) : undefined;
            fetchAndRender({ pageParams, resetPage: false });
        }
    }

    document.querySelectorAll('form[data-live-search]').forEach(initOne);
})();


// ============================================================================
// 10. Globals exposed for inline scripts + Livewire/Flux components
// ============================================================================

// Bootstrap is consumed both via data-bs-* (no global needed) and via
// programmatic calls in inline scripts (e.g. admin modals), so re-export.
window.bootstrap = bootstrap;

// Passkeys is consumed by the passkey-registration / passkey-verify Alpine
// components. They listen for `passkeys:ready` to re-check support once the
// helper has finished loading.
window.Passkeys = Passkeys;
window.dispatchEvent(new CustomEvent('passkeys:ready'));
