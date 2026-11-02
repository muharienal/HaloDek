<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian: ->middleware('role:superadmin,kepala_regu')
 * Parameter khusus "admin" = semua role admin.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = $user && (
            in_array($user->role, $roles, true)
            || (in_array('admin', $roles, true) && $user->isAdmin())
        );

        abort_unless($allowed, 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
