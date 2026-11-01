<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: permission:accounting.expenses.create */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        foreach ($permissions as $p) {
            if (! $user || ! $user->hasPermission($p)) {
                abort(403, 'You do not have permission: '.$p);
            }
        }

        return $next($request);
    }
}
