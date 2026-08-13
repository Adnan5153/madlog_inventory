// =============================================================================
// Madlog Store — landing bundle entry point
// =============================================================================
// Bootstrap bundle includes Popper, so we don't need @popperjs/core.
// Side-effect import: this auto-wires data-bs-toggle="collapse", data-bs-toggle="dropdown",
// data-bs-toggle="modal", data-bs-toggle="tooltip" without any manual init.
// IMPORTANT: this import has side effects; do NOT remove the `import 'bootstrap'`
// line, even though it looks unused — it's what makes the `data-bs-*` attributes work.
import * as bootstrap from 'bootstrap';

// --- Scroll-spy for navbar active state --------------------------------------
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

// --- Collapse mobile navbar after a link is tapped --------------------------
document.querySelectorAll('.navbar-collapse .nav-link').forEach((link) => {
    link.addEventListener('click', () => {
        const collapseEl = document.getElementById('mainNav');
        if (collapseEl && collapseEl.classList.contains('show')) {
            // eslint-disable-next-line no-undef
            const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            collapse.hide();
        }
    });
});