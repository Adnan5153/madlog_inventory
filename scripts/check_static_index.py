"""Quick sanity check for the rebuilt index.html."""
from pathlib import Path

INDEX = Path("index.html")
TEXT = INDEX.read_text(encoding="utf-8")

checks = {
    "navbar handler comment present":
        "Vanilla navbar-toggler handler" in TEXT,
    "toggler.addEventListener present":
        "toggler.addEventListener" in TEXT,
    "matchMedia() present":
        "matchMedia(" in TEXT,
    "aria-expanded sync present":
        "toggler.setAttribute('aria-expanded'" in TEXT,
    "fonts.googleapis.com count (expect 2: preconnect + stylesheet)":
        TEXT.count("fonts.googleapis.com"),
    "favicon link count (expect 1)":
        TEXT.count('rel="icon" type="image/svg+xml"'),
    "data:font/woff2 data URIs (expect 1)":
        TEXT.count("data:font/woff2;base64,"),
    "/build/assets count (expect 0)":
        TEXT.count("/build/assets/"),
    "127.0.0.1 count (expect 0)":
        TEXT.count("127.0.0.1"),
    "/login URL count (expect 0)":
        len([line for line in TEXT.splitlines() if 'href="/login"' in line]),
    "mailto login count (expect 1)":
        TEXT.count("mailto:hello@madlogstore.test?subject=Madlog%20Store%20login%20request"),
    "navbar toggler button still present":
        'data-bs-toggle="collapse"' in TEXT,
}

for name, value in checks.items():
    if isinstance(value, bool):
        ok = value
    elif isinstance(value, int):
        if "expect 0" in name:
            ok = value == 0
        elif "expect 1" in name:
            ok = value == 1
        elif "expect 2" in name:
            ok = value == 2
        else:
            ok = True  # no expectation specified
    else:
        ok = True
    marker = "OK " if ok else "FAIL"
    print(f"[{marker}] {name}: {value}")

# Overall size
kb = INDEX.stat().st_size / 1024
print(f"\nFile size: {kb:.1f} KB")
