<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memanjangkan lifetime session (cookie + validasi DB) untuk halaman monitoring dashboard.
 * Harus dijalankan sebelum StartSession agar DatabaseSessionHandler memakai menit yang benar.
 */
class SetLongSessionForDashboardRoutes
{
    /** 30 hari dalam menit */
    public const LIFETIME_MINUTES = 43200;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('dashboard') || $request->is('dashboard/*')) {
            config(['session.lifetime' => self::LIFETIME_MINUTES]);
        }

        return $next($request);
    }
}
