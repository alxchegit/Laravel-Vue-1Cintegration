<?php


namespace App\Helpers;


use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;

class WebCService extends ApiResponse
{

    private static function getHeaders()
    {
        return [
            'Authorization' => env('CI_AUTHORIZATION_HEADER'),
            'Referer' => env('CI_REFERER')
        ];
    }
    /**
     * @param string $point
     * @param array $data
     * @return ApiResponse
     */
    public static function get(string $point, array $data = []): ApiResponse
    {
        $response = Http::withHeaders(self::getHeaders())->get(env('CI_BASE_URL') . $point, $data);

        return self::response($response);
    }

    /**
     * POST
     * @param string $point
     * @param array $data
     * @return ApiResponse
     */
    public static function post(string $point, array $data): ApiResponse
    {
        $response = Http::withHeaders(self::getHeaders())->post(env('CI_BASE_URL') . $point, $data);

        return self::response($response);
    }
    /**
     * @param string $point
     * @param array $data
     * @return ApiResponse
     */
    public static function put(string $point, array $data = []): ApiResponse
    {
        $response = Http::withHeaders(self::getHeaders())->put(env('CI_BASE_URL') . $point, $data);

        return self::response($response);
    }

    /**
     * @param HttpResponse $response
     * @return WebCService
     */
    private static function response(HttpResponse $response): ApiResponse
    {
        if (!$response->ok()) {
            return self::error([
                'resp_status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);
        }
        return self::success([
            'response' => $response->json()['data'],
            'status' => $response->json()['status']
        ]);
    }

}
