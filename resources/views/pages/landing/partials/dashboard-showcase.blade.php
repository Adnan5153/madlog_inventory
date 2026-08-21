<section id="showcase" class="section bg-dark text-light">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 720px;">
            <p class="eyebrow text-primary mb-2">THE PRODUCT</p>
            <h2 class="display-5 fw-bold mb-3 text-balance">One Dashboard. Every Bay.</h2>
            <p class="lead opacity-75">
                See live inventory, low-stock alerts, and job-card consumption from a single screen — built for the realities of a working storeroom.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                @include('pages.landing.partials.dashboard-mockup', ['variant' => 'showcase'])
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-md-4">
                <div class="mockup-stat-tile d-flex align-items-center gap-3 h-100">
                    <i class="bi-graph-up mockup-stat-tile__icon" aria-hidden="true"></i>
                    <div>
                        <div class="mockup-stat-tile__label">Stock movement</div>
                        <div class="small opacity-75 mb-0">Sample visualisation — wires to your live data.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mockup-stat-tile d-flex align-items-center gap-3 h-100">
                    <i class="bi-exclamation-triangle mockup-stat-tile__icon" aria-hidden="true"></i>
                    <div>
                        <div class="mockup-stat-tile__label">Reorder alerts</div>
                        <div class="small opacity-75 mb-0">Threshold-driven, not after-the-fact.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mockup-stat-tile d-flex align-items-center gap-3 h-100">
                    <i class="bi-clipboard-data mockup-stat-tile__icon" aria-hidden="true"></i>
                    <div>
                        <div class="mockup-stat-tile__label">Job card activity</div>
                        <div class="small opacity-75 mb-0">Parts consumed per repair, visible live.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>