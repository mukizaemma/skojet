<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReservationsManager
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! reservations_manager_enabled()) {
            abort(403, 'Reservations Manager is not active for this hotel.');
        }

        return $next($request);
    }
}
