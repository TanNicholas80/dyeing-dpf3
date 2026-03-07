<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use App\Models\Approval;
use App\Models\Proses;
use App\Observers\ApprovalObserver;
use App\Observers\ProsesObserver;
use App\Services\ApprovalCacheService;
use App\Services\MesinCacheService;

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
        // Set timezone global
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        // Set Carbon locale (tanpa setToStringFormat karena deprecated)
        Carbon::setLocale('id');
        // Carbon::setToStringFormat('d-m-Y H:i:s'); // HAPUS BARIS INI

        View::composer('layout.main', function ($view) {
            $view->with('mesins', app(MesinCacheService::class)->getSelectionList());

            $counts = app(ApprovalCacheService::class)->getPendingCounts();
            $view->with('pendingApprovalFM', $counts['pendingApprovalFM']);
            $view->with('pendingApprovalVP', $counts['pendingApprovalVP']);
            $view->with('pendingApprovalKepalaShift', $counts['pendingApprovalKepalaShift']);
        });

        Proses::observe(ProsesObserver::class);
        Approval::observe(ApprovalObserver::class);
    }
}
