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

    public function handle(Request $request, Closure $next, string $pageKey, ?string $permission = null): Response
    {
        $user = $request->user();
        $authorized = $user && ($permission === 'edit'
            ? $this->access->canEdit($user, $pageKey)
            : $this->access->canAccess($user, $pageKey));

        if (! $authorized) {
            return response()->json(['message' => 'Kamu tidak punya akses ke halaman ini.'], 403);
        }

        return $next($request);
    }
}
