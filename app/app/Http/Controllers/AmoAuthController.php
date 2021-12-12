<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use App\Helpers\ApiResponse;
use App\Models\AuthData;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmoAuthController extends Controller
{
    /**
     * Webhook redirect_uri
     * @param  Request  $request
     * @param  AmoCRMApiClient  $apiClient
     * @return JsonResponse
     */
    public function redirectUri(Request $request, AmoCRMApiClient $apiClient): JsonResponse
    {
        try {
            $apiClient->setAccountBaseDomain($request->get('referer'));
            $token = $apiClient->getOAuthClient()->getAccessTokenByCode($request->get('code'));
            $apiClient->setAccessToken($token);
            $data = AuthData::query()->updateOrCreate(['account_id' => $apiClient->account()->getCurrent()->getId()], [
                AuthData::BASE_DOMAIN => $apiClient->getAccountBaseDomain(),
                AuthData::REFRESH_TOKEN => $token->getRefreshToken(),
                AuthData::EXPIRES => Carbon::createFromTimestamp($token->getExpires())->toDateTimeString(),
                AuthData::ACCESS_TOKEN => $token->getToken()
            ]);
            return response()->json($data);
        } catch (\Throwable $exception) {
            Log::error(
                'Ошибка во время получения токена аккаунт - '.$request->get(
                    'referer'
                ).' Ошибка '.$exception->getMessage()
            );
            return ApiResponse::exception($exception);
        }
    }
}
