<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API untuk aplikasi Inventaris: wajib header "Authorization: Bearer <INVENTARIS_API_TOKEN>".
 * Token yang sama dipakai dua arah (HaloDek -> Inventaris dan Inventaris -> HaloDek).
 * Token kosong / kurang dari 16 karakter = API dimatikan.
 */
class ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.inventaris.token');
        $given = (string) $request->bearerToken();

        if (strlen($expected) < 16) {
            return response()->json(['message' => 'API belum diaktifkan. Isi INVENTARIS_API_TOKEN (min. 16 karakter) di .env.'], 503);
        }
        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Token API tidak valid.'], 401);
        }

        return $next($request);
    }
}
