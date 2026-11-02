<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 419 (token CSRF kedaluwarsa) jangan berakhir di halaman error.
        // Pengguna diarahkan kembali dengan token baru dan pesan yang ramah.
        $this->renderable(function (HttpException $e, Request $request): ?Response {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $input = $request->except(['_token', 'password', 'password_confirmation', 'current_password']);

            if (! $request->user()) {
                return redirect()->route('login')
                    ->withInput($input)
                    ->with('warning', 'Halaman login sempat kedaluwarsa. Silakan masuk kembali.');
            }

            return redirect()->back(fallback: route('dashboard'))
                ->withInput($input)
                ->with('error', 'Sesi formulir sempat kedaluwarsa, data belum tersimpan. Silakan kirim ulang.');
        });
    }
}
