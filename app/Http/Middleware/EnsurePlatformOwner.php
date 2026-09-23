<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->is_active
            && $user->hasRole('admin')
            && hash_equals(mb_strtolower((string) config('platform.owner_email')), mb_strtolower($user->email)),
            403,
        );

        return $next($request);
    }
}
