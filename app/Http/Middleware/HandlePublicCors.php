<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlePublicCors
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isMatchingPath($request)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers', 'Authorization, Content-Type, Accept, Mcp-Session-Id, X-Requested-With, X-Inertia'),
                'Access-Control-Expose-Headers' => 'Mcp-Session-Id, WWW-Authenticate, Location, X-Inertia-Location',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'Mcp-Session-Id, WWW-Authenticate, Location, X-Inertia-Location');

        return $response;
    }

    /**
     * Determine if the request matches public MCP or OAuth paths.
     */
    protected function isMatchingPath(Request $request): bool
    {
        return $request->is('api/mcp')
            || $request->is('oauth/*')
            || $request->is('.well-known/*');
    }
}
