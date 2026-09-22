<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin bisa akses semua
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Cek apakah user role termasuk dalam roles yang diizinkan (dukung kepala_regu & kepala_ruangan)
        $allowedRoles = $roles;
        if (in_array('kepala_regu', $roles, true) || in_array('kepala_ruangan', $roles, true)) {
            $allowedRoles[] = 'kepala_regu';
            $allowedRoles[] = 'kepala_ruangan';
        }

        if (in_array($user->role, $allowedRoles, true)) {
            return $next($request);
        }

        // Pendelegasian dinamis bila salah satu role berstatus OFF (Absen)
        $isKaruUser = in_array($user->role, ['kepala_regu', 'kepala_ruangan'], true);
        if (in_array('kepala_shift', $roles, true) && $isKaruUser && \App\Services\AbsenService::isKashiftOff()) {
            return $next($request);
        }

        $requiresKaru = in_array('kepala_regu', $roles, true) || in_array('kepala_ruangan', $roles, true);
        if ($requiresKaru && $user->role === 'kepala_shift' && \App\Services\AbsenService::isKaruOff()) {
            return $next($request);
        }

        // Redirect jika tidak memiliki akses (hindari loop: jangan kirim ke dashboard jika role tidak punya route dashboard)
        if ($user->role === 'aux') {
            return redirect()->route('aux.index')->with('error', 'Anda tidak memiliki akses');
        } elseif ($user->role === 'dye_stuff') {
            return redirect()->route('dye-stuff.index')->with('error', 'Anda tidak memiliki akses');
        } elseif ($user->role === 'spv_listrik') {
            return redirect()->route('mesin.index')->with('error', 'Anda tidak memiliki akses');
        }

        $dashboardRoles = [
            'super_admin', 'ds', 'dye_stuff', 'mesin', 'ppic', 'fm', 'vp', 'owner',
            'kepala_regu', 'kepala_ruangan', 'kepala_shift', 'dashboard', 'operator', 'scm'
        ];
        if (in_array($user->role, $dashboardRoles, true)) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki akses');
        }

        return redirect()->route('user.profile')->with('error', 'Anda tidak memiliki akses');
    }
}
