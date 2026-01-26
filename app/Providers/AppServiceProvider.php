<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use App\Models\Mesin;
use App\Models\Approval;
use App\Models\Proses;
use App\Observers\ProsesObserver;

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
            $view->with('mesins', Mesin::all());
            
            // Hitung jumlah pending approvals untuk FM dan VP
            $pendingApprovalFM = Approval::where('type', 'FM')
                ->where('status', 'pending')
                ->count();
            
            $pendingApprovalVP = Approval::where('type', 'VP')
                ->where('status', 'pending')
                ->count();
            
            $view->with('pendingApprovalFM', $pendingApprovalFM);
            $view->with('pendingApprovalVP', $pendingApprovalVP);
        });

        // Register observer untuk Proses model
        Proses::observe(ProsesObserver::class);
    }
}
