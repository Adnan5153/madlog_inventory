<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\BinLocation;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\GoodsReceipt;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SerialNumber;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use App\Observers\AuditObserver;
use App\Observers\PartObserver;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SettingService is a singleton — its cache is meaningful only
        // when shared across the request lifetime.
        $this->app->singleton(SettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSettingHelper();
        $this->registerAuditObservers();
    }

    /**
     * Attach AuditObserver to every model whose changes need an audit
     * trail. Models are listed explicitly (opt-in) so we don't audit
     * every Eloquent model in the app.
     */
    protected function registerAuditObservers(): void
    {
        $observed = [
            Workshop::class,
            PartCategory::class,
            Brand::class,
            Unit::class,
            Supplier::class,
            SupplierCategory::class,
            Part::class,
            Equipment::class,
            Department::class,
            Setting::class,
            User::class,
            BinLocation::class,
            PurchaseOrder::class,
            GoodsReceipt::class,
            StockAdjustment::class,
            StockTransfer::class,
            Batch::class,
            SerialNumber::class,
            Role::class,
            Permission::class,
        ];

        foreach ($observed as $class) {
            $class::observe(AuditObserver::class);
        }

        // Domain-event observers (not audit, but Eloquent hooks).
        Part::observe(PartObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Load the helpers file that defines the global `setting()` helper.
     */
    protected function registerSettingHelper(): void
    {
        $path = app_path('helpers.php');
        if (file_exists($path)) {
            require_once $path;
        }
    }
}