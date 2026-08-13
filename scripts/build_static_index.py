#!/usr/bin/env python3
"""
Build a self-contained `index.html` for GitHub Pages (or any static host).

Why this exists
---------------
The static `index.html` checked-in at the repo root is a snapshot of the
landing page rendered by Laravel. It references:

  - http://127.0.0.1:8000/build/assets/...   (dev-server only)
  - /build/assets/instrument-sans-*.woff2    (font files)
  - /build/assets/bootstrap-icons-*.woff2    (icon font files)
  - ./build/assets/landing-*.css             (landing bundle)
  - ./build/assets/landing-*.js              (scroll-spy + collapse glue)
  - ./build/assets/bootstrap-*.js            (Bootstrap JS bundle)
  - http://127.0.0.1:8000/login              (server route)

All of those break the moment the page is served from a static host
(GitHub Pages, Netlify, S3, etc.) — `public/build/` is gitignored, so
the asset files never make it to the repo, and there's no server to
handle `/login`.

What this script does
---------------------
1. Reads the source `index.html`.
2. Reads `public/build/assets/landing-*.css` and replaces the two
   `url(/build/assets/bootstrap-icons-*.woff2?)` references with
   base64-encoded `data:font/woff2;base64,…` data URIs — so the icon
   font is shipped inline with the CSS.
3. Reads the same again for the `.woff` (legacy) variant.
4. Replaces the Instrument Sans font-face block to point at Google Fonts
   CDN with a system-font fallback (no inline font data needed).
5. Inlines the landing JS and a tiny vanilla-JS bootstrap-substitute
   (scroll-spy + collapse-on-click) so we don't need the bootstrap JS
   bundle.
6. Rewrites every `http://127.0.0.1:8000/...` URL to a relative path
   (which is the only thing that works on GitHub Pages sub-paths).
7. Rewrites the `/login` link to a `mailto:hello@madlogstore.test`
   link (the live auth lives on the Laravel server, not Pages).

Result: a single HTML file. No external files. No PHP. No server.
Works on GitHub Pages, S3, Netlify, opening the file directly via
file://, etc.

Run with:

    git show e930ace:index.html > index.original.html   # one-time
    python scripts/build_static_index.py

The script reads `index.original.html` (the pristine Livewire
snapshot, kept under git so this rebuild is reproducible) and writes
the self-contained result to `index.html`. Don't run it on the
already-built `index.html` — it's idempotent but cleaner to always
start from the snapshot.
"""
from __future__ import annotations

import base64
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "index.html"
# The pristine Livewire snapshot, never modified by this script.
# Re-extracted from the very first commit when this script was added
# so the build always starts from a known-good input.
ORIGINAL = ROOT / "index.original.html"
BUILD_DIR = ROOT / "public" / "build" / "assets"


def find_asset(pattern: str) -> Path:
    """Return the first asset matching `*.glob`-style pattern under build dir."""
    matches = sorted(BUILD_DIR.glob(pattern))
    if not matches:
        raise FileNotFoundError(f"No asset matching {pattern} in {BUILD_DIR}")
    return matches[0]


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def b64(path: Path) -> str:
    return base64.b64encode(path.read_bytes()).decode("ascii")


def inline_bootstrap_icons_fonts(css: str) -> str:
    """Rewrite the two @font-face src URLs to inline data URIs."""
    woff2 = find_asset("bootstrap-icons-*.woff2")
    woff = find_asset("bootstrap-icons-*.woff")

    woff2_b64 = b64(woff2)
    woff_b64 = b64(woff)

    # CSS uses generated hashes (?e34853135…). The format is
    # url(/build/assets/bootstrap-icons-<hash>.woff2?<query>).
    # We rewrite the URL form to a data URI — the ?query becomes
    # irrelevant since the data is already in the URL.
    css = re.sub(
        r"url\(/build/assets/bootstrap-icons-[^)]+\.woff2\?[^)]+\)",
        f"url(data:font/woff2;base64,{woff2_b64})",
        css,
    )
    css = re.sub(
        r"url\(/build/assets/bootstrap-icons-[^)]+\.woff\?[^)]+\)",
        f"url(data:font/woff;base64,{woff_b64})",
        css,
    )
    return css


def replace_google_fonts_in_head(html: str) -> str:
    """Replace the Instrument Sans @font-face block + preload tags with
    a Google Fonts CDN link. Drop the inline @font-face and preloads
    since the Google Fonts CSS handles all that for us. Idempotent:
    running on a previously-built file is a no-op for the Google Fonts
    insertion.
    """
    # 1. Drop the entire <style>…</style> block containing the Instrument Sans
    #    @font-face rules (we keep its `--font-instrument-sans` variable).
    instrument_face_re = re.compile(
        r"<style>\s*@font-face \{[^}]*font-family:\s*\"Instrument Sans\"[^}]*\}.*?</style>",
        re.DOTALL,
    )
    html = instrument_face_re.sub(
        """<style>
:root {
  --font-instrument-sans: "Instrument Sans", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
.font-instrument-sans {
  font-family: var(--font-instrument-sans);
}
</style>""",
        html,
        count=1,
    )

    # 2. Drop the three <link rel="preload" as="font" ...> tags pointing
    #    at Instrument Sans woff2 files.
    html = re.sub(
        r'<link rel="preload" as="font" href="http://127\.0\.0\.1:8000/build/assets/instrument-sans-[^"]+"\s*type="font/woff2"\s*crossorigin="anonymous"\s*/>\s*',
        "",
        html,
    )

    # 3. Insert a Google Fonts <link> right after <title>…</title>.
    #    We use the canonical CSS variable name from the site.
    #    Idempotent: if the link is already there (e.g. we're rebuilding
    #    on top of a previously-built file), skip the insertion.
    if "fonts.googleapis.com/css2?family=Instrument+Sans" not in html:
        html = html.replace(
            "</title>",
            '</title>\n'
            '<link rel="preconnect" href="https://fonts.googleapis.com">\n'
            '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&display=swap">',
            1,
        )
    return html


def inline_landing_css(html: str, css: str) -> str:
    """Replace the <link rel="stylesheet" …landing-*.css…> with an
    inline <style> block containing the rewritten CSS.

    The Livewire-snapshot original can have these tags in two layouts:
    all on one line, or split across multiple lines. We strip them one
    at a time so we don't have to match a single multi-tag regex.
    """
    css = inline_bootstrap_icons_fonts(css)
    inline_style = f"<style>\n{css}\n</style>"

    # Drop the preload <link> for the CSS.
    html = re.sub(
        r'<link rel="preload" as="style" href="http://127\.0\.0\.1:8000/build/assets/landing-[^"]+\.css"\s*/>',
        "",
        html,
    )
    # Drop the two modulepreload <link> tags (landing JS + bootstrap JS).
    html = re.sub(
        r'<link rel="modulepreload" as="script" href="http://127\.0\.0\.1:8000/build/assets/(?:bootstrap|landing)-[^"]+\.js"\s*/>',
        "",
        html,
    )
    # Drop the actual <link rel="stylesheet"> — and replace it with our
    # inlined <style> block in the same position.
    html = re.sub(
        r'<link rel="stylesheet" href="http://127\.0\.0\.1:8000/build/assets/landing-[^"]+\.css"(?:\s+data-navigate-track="reload")?\s*/>',
        inline_style,
        html,
        count=1,
    )
    return html


def inline_landing_js(html: str) -> str:
    """Replace the <script type="module" src="…/landing-*.js"> tag with
    a plain <script> block. The original imports Bootstrap's `Collapse`
    module which we don't have access to — we replace the call with
    vanilla `classList.remove('show')`.
    """
    landing_js = read_text(find_asset("landing-*.js"))

    rewrites = [
        (
            r'import\{n as e\}from"\./bootstrap-[^"]+\.js";',
            "",
        ),
        (
            r"t&&t\.classList\.contains\(`show`\)&&e\.getOrCreateInstance\(t,\{toggle:!1\}\)\.hide\(\)",
            "t&&t.classList.contains(`show`)&&t.classList.remove(`show`)",
        ),
    ]
    for pattern, replacement in rewrites:
        landing_js = re.sub(pattern, replacement, landing_js)

    # Vanilla navbar-toggler handler. Without Bootstrap's JS bundle the
    # data-bs-toggle attribute does nothing, so the hamburger button
    # silently fails on mobile. We attach click + resize handlers that
    # mirror the bits of Collapse.data-api this page actually needs.
    # `992px` mirrors Bootstrap's `lg` breakpoint; the navbar carries
    # `navbar-expand-lg`, so the menu collapses below that width.
    navbar_js = """\
// === Vanilla navbar-toggler handler ========================================
// Replaces Bootstrap's Collapse data-API for the one navbar on this page.
//   - Click the toggler to expand/collapse #mainNav.
//   - Sync `aria-expanded` so screen readers + the visual hamburger
//     stay in sync.
//   - On click of any nav-link inside the open menu, close the menu
//     (already handled by the inlined landing JS above).
//   - When the viewport crosses the lg breakpoint (992px) and the menu
//     was left open on mobile, close it so we don't strand a stuck-open
//     dropdown at desktop sizes.
// ==========================================================================
(function () {
    'use strict';
    var breakpoint = 992; // Bootstrap `lg`
    var toggler = document.querySelector('.navbar-toggler');
    var target = document.querySelector('#mainNav');
    if (!toggler || !target) return;

    toggler.addEventListener('click', function () {
        var expanded = toggler.getAttribute('aria-expanded') === 'true';
        toggler.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        target.classList.toggle('show');
    });

    // Aria-controls is wired by the HTML — nothing to do there.
    // Auto-close when crossing the breakpoint so a mobile-open menu
    // doesn't stay visually stacked over the desktop layout.
    var mq = window.matchMedia('(min-width: ' + breakpoint + 'px)');
    var syncOnResize = function (e) {
        if (e.matches && target.classList.contains('show')) {
            target.classList.remove('show');
            toggler.setAttribute('aria-expanded', 'false');
        }
    };
    if (mq.addEventListener) {
        mq.addEventListener('change', syncOnResize);
    } else if (mq.addListener) {
        // Safari < 14 fallback.
        mq.addListener(syncOnResize);
    }
})();
"""

    inline = f"<script>\n{landing_js}\n{navbar_js}\n</script>"
    return re.sub(
        r'<script type="module" src="http://127\.0\.0\.1:8000/build/assets/landing-[^"]+\.js"[^>]*></script>',
        inline,
        html,
        count=1,
    )


def insert_login_button(html: str) -> str:
    """Insert the navbar Login button into the static page.

    The original snapshot from the first commit doesn't have one — the
    Login button was added later in the Blade template. To keep the
    static page in sync, this step injects the same `<li>` markup the
    Blade partial renders, pointing at `mailto:` since the live auth
    flow lives on the Laravel server, not the static page.

    Idempotent: if the Login button is already present (rebuild on top
    of a previously-built file), it's not duplicated. The detector
    looks for the button's text content (`>Login</a>`); checking just
    for `bi-box-arrow-in-right` would also match the inlined icon
    font's CSS rule defining the glyph.
    """
    if re.search(r">\s*Login\s*</a>", html):
        return html

    login_li = (
        '<li class="nav-item ms-lg-3 mt-2 mt-lg-0">'
        '<a class="btn btn-outline-light fw-semibold w-100" '
        'href="mailto:hello@madlogstore.test?subject=Madlog%20Store%20login%20request">'
        '<i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Login'
        '</a>'
        '</li>\n                '
    )

    # Insert immediately before the "Start Free Trial" <li>.
    pattern = (
        r'(<li class="nav-item ms-lg-3 mt-2 mt-lg-0">\s*'
        r'<a class="btn btn-outline-light fw-semibold w-100" href="#final-cta">Start Free Trial</a>\s*'
        r'</li>)'
    )
    return re.sub(pattern, login_li + r'\1', html, count=1)


def drop_livewire_attrs(html: str) -> str:
    """Strip `data-navigate-track="reload"` from the original Livewire
    snapshot — it has no meaning on a static page.
    """
    return html.replace(' data-navigate-track="reload"', "")


def inline_favicon(html: str) -> str:
    """Replace the three site-root-relative favicon <link> tags with a
    single inline data-URI SVG icon (the same `bi-tools` glyph that's
    used in the brand mark). The absolute paths would 404 on
    GitHub-Pages project sites since the page is mounted at /<repo>/.
    """
    # Yellow wrench-on-dark SVG that matches the navbar brand mark.
    favicon_svg = (
        "data:image/svg+xml;utf8,"
        "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'>"
        "<rect width='24' height='24' rx='4' fill='%230f172a'/>"
        "<path fill='%23f59e0b' d='M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.4-2.4 2.4-2.4Z'/>"
        "</svg>"
    )
    inline = (
        f'<link rel="icon" type="image/svg+xml" href="{favicon_svg}">'
    )
    # Remove the three original favicon links (they may be on the same
    # line or split — handle each pattern independently).
    for pattern in [
        r'<link rel="icon" href="/favicon\.ico" sizes="any">\s*',
        r'<link rel="icon" href="/favicon\.svg" type="image/svg\+xml">\s*',
        r'<link rel="apple-touch-icon" href="/apple-touch-icon\.png">\s*',
    ]:
        html = re.sub(pattern, "", html)
    # Idempotent: if the inline SVG favicon is already in place, don't
    # insert another.
    if "rel=\"icon\" type=\"image/svg+xml\" href=\"data:image/svg+xml" in html:
        return html
    return html.replace(
        '<meta name="description"',
        f'{inline}\n<meta name="description"',
        1,
    )


def main() -> None:
    if not ORIGINAL.exists():
        raise SystemExit(
            f"Missing {ORIGINAL.name}. Restore it with: "
            f"git show e930ace:index.html > {ORIGINAL}"
        )

    html = read_text(ORIGINAL)
    css = read_text(find_asset("landing-*.css"))

    html = replace_google_fonts_in_head(html)
    html = inline_landing_css(html, css)
    html = inline_landing_js(html)
    html = insert_login_button(html)
    html = drop_livewire_attrs(html)
    html = inline_favicon(html)

    SOURCE.write_text(html, encoding="utf-8")
    kb = SOURCE.stat().st_size / 1024
    print(f"Wrote {SOURCE} ({kb:.1f} KB)")


if __name__ == "__main__":
    main()
