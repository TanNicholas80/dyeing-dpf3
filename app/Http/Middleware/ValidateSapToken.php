<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSapToken
{
    /**
     * Handle an incoming request from SAP.
     *
     * Memeriksa keberadaan dan keabsahan token API SAP.
     * Mendukung:
     * 1. Authorization: Bearer <TOKEN>
     * 2. X-SAP-TOKEN: <TOKEN>
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = env('SAP_API_TOKEN');

        // Jika token belum diset di .env, jangan izinkan akses demi keamanan
        if (empty($expectedToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Konfigurasi SAP_API_TOKEN belum diset di server.',
            ], 500);
        }

        // Ambil token dari Authorization Bearer atau Custom Header
        $providedToken = $request->bearerToken()
            ?: $request->header('X-SAP-TOKEN')
            ?: $request->header('X-DEVICE-TOKEN');

        if (empty($providedToken) || !hash_equals((string) $expectedToken, (string) $providedToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Token SAP tidak valid atau tidak disertakan.',
            ], 401);
        }

        return $next($request);
    }
}
