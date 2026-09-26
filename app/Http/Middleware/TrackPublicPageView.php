<?php

namespace App\Http\Middleware;

use App\Support\PublicAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPublicPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            app(PublicAnalytics::class)->record($request, 'page_view', '/'.$request->path());
        }

        return $response;
    }
}
