<?php

namespace App\Http\Middleware;

use App\Support\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! app(ModuleManager::class)->enabled($module)) {
            return $request->routeIs('home') ? redirect()->route('login') : abort(404);
        }

        return $next($request);
    }
}
