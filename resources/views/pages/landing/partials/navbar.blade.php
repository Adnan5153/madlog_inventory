<nav class="navbar navbar-expand-lg sticky-top bg-dark" data-bs-theme="dark" aria-label="Primary">
    <div class="container">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="#top">
            <i class="bi-tools text-warning" aria-hidden="true"></i>
            <span>Madlog</span>
            <span class="text-warning">Store</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" data-nav-link="features" href="#features">Product</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-nav-link="workflow" href="#workflow">Workflow</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-nav-link="pricing" href="#pricing">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-nav-link="faq" href="#faq">FAQ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-nav-link="final-cta" href="#final-cta">Contact</a>
                </li>
                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                    <a class="btn btn-outline-light fw-semibold w-100" href="{{ url('/login') }}">
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Login
                    </a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn btn-outline-light fw-semibold w-100" href="#final-cta">Start Free Trial</a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn btn-warning fw-semibold w-100" href="#final-cta">Request Demo</a>
                </li>
            </ul>
        </div>
    </div>
</nav>