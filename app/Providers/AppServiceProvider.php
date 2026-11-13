<?php

namespace App\Providers;

use App\Models\AirConditioner;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Observers\AirConditionerObserver;
use App\Observers\InvoiceObserver;
use App\Observers\MaintenanceRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AirConditioner::observe(AirConditionerObserver::class);
        Invoice::observe(InvoiceObserver::class);
        MaintenanceRecord::observe(MaintenanceRecordObserver::class);
    }
}
