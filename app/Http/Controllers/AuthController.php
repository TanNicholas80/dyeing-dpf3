<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLongSessionForDashboardRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function login()
    {
        if (Auth::check()) {
            $user = Auth::user();
            // Redirect berdasarkan role
            if ($user->role === 'aux') {
                return redirect()->route('aux.index');
            }

            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Proses login user.
     */
    public function login_proses(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('username', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            // Pastikan tidak ada URL intended tersisa yang bisa mengarahkan ke route yang tidak diizinkan
            $request->session()->forget('url.intended');

            // Redirect berdasarkan role
            $user = Auth::user();
            // Monitoring Smart TV (role dashboard): session panjang agar tidak logout berulang
            if ($user->role === 'dashboard') {
                config(['session.lifetime' => SetLongSessionForDashboardRoutes::LIFETIME_MINUTES]);
            }
            $redirectRoute = 'dashboard';
            if ($user->role === 'aux') {
                $redirectRoute = 'aux.index';
            }

            return redirect()
                ->route($redirectRoute)
                ->with('success', 'Login berhasil. Selamat datang, '.$user->nama.'!');
        }

        return back()
            ->withInput($request->only('username'))
            ->with('error', 'Username atau password salah');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Logout berhasil.');
    }
}
