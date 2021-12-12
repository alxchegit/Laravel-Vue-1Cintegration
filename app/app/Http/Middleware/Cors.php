<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $handle = $next($request);
        if(method_exists($handle, 'header')) {
            $handle->header('Access-Control-Allow-Origin', '*');
        }
        return $handle;
    }
}
