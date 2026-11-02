<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman HTML (terutama form login) tidak boleh disimpan di cache browser /
 * tombol "Back". Halaman lama membawa token CSRF lama sehingga saat dikirim
 * hasilnya 419 "Page Expired". Dengan no-store, browser selalu mengambil
 * halaman baru beserta token yang valid.
 */
class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
