<?php

use App\Http\Middleware\SetLongSessionForDashboardRoutes;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // tambahkan baris ini
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', [
            SetLongSessionForDashboardRoutes::class,
        ]);
        $middleware->redirectUsersTo(function (Request $request) {
            $user = Auth::user();

            if ($user?->role === 'aux') {
                return route('aux.index');
            }

            return route('dashboard');
        });
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'prevent-back-history' => PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                $user = Auth::user();

                return response()->json([
                    'message' => 'Token CSRF kedaluwarsa.',
                    'redirect' => $user
                        ? ($user->role === 'aux' ? route('aux.index') : route('dashboard'))
                        : route('login'),
                ], 419);
            }

            if (Auth::check()) {
                $user = Auth::user();
                if ($user->role === 'aux') {
                    return redirect()->route('aux.index');
                }

                return redirect()->route('dashboard');
            }

            return redirect()
                ->route('login')
                ->with('error', 'Sesi atau token keamanan kedaluwarsa. Silakan login kembali.');
        });
    })->create();
