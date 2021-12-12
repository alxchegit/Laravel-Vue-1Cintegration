<?php

namespace App\Http\Middleware;

use AmoCRM\Client\AmoCRMApiClient;
use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class AmoAuthorize
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  AmoCRMApiClient  $amoCRMApiClient
     * @return mixed
     */
    public function handle(Request $request, Closure $next, AmoCRMApiClient $amoCRMApiClient)
    {
        try {
            $parsed = $amoCRMApiClient->getOAuthClient()->parseDisposableToken($request->header('x-auth-token'));
            $request->headers->set('account_id', $parsed->getAccountId());
            $request->headers->set('user_id', $parsed->getUserId());
            return $next($request);
        } catch (Throwable $e) {
            return ApiResponse::exception($e);
        }
    }
}
