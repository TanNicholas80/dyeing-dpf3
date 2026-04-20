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

        // Cek apakah user role termasuk dalam roles yang diizinkan
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // Redirect jika tidak memiliki akses (hindari loop: jangan kirim ke dashboard jika role tidak punya route dashboard)
        if ($user->role === 'aux') {
            return redirect()->route('aux.index')->with('error', 'Anda tidak memiliki akses');
        }

        $dashboardRoles = [
            'super_admin', 'ds', 'mesin', 'ppic', 'fm', 'vp', 'owner',
            'kepala_ruangan', 'kepala_shift', 'dashboard', 'operator',
        ];
        if (in_array($user->role, $dashboardRoles, true)) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki akses');
        }

        return redirect()->route('user.profile')->with('error', 'Anda tidak memiliki akses');
    }
}
