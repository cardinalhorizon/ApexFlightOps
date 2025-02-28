<?php

namespace Modules\ApexFlightOps\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Class ResponseHeaders
 * @package Modules\ApexFlightOps\Http\Middleware
 */
class ResponseHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (env('APEX_CORS_ALLOW_ORIGIN')) {
            $response->header('Access-Control-Allow-Origin', env('APEX_CORS_ALLOW_ORIGIN', $request->getHost()));
        }
        return $response;
    }
}
