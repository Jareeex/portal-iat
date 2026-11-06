<?php

namespace App\Http\Middleware;

use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di route: ->middleware('page.access:reports')
 * Master selalu lolos, user biasa harus sudah di-assign halaman tsb oleh master.
 */
class CheckPageAccess
{
    public function __construct(private AccessService $access) {}

    public function handle(Request $request, Closure $next, string $pageKey): Response
    {
        $user = $request->user();

        if (! $user || ! $this->access->canAccess($user, $pageKey)) {
            return response()->json(['message' => 'Kamu tidak punya akses ke halaman ini.'], 403);
        }

        return $next($request);
    }
}
