<?php


namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse extends JsonResponse
{
    public static function success($data = null, int $code = Response::HTTP_OK): self
    {
        $result = ['success' => true];

        if ($data !== null) {
            $result['payload'] = $data;
        }

        return new self($result, $code);
    }

    public static function error($data = null, int $code = Response::HTTP_BAD_REQUEST): self
    {
        $result = ['success' => false];

        if ($data !== null) {
            $result['error'] = $data;
        }

        return new self($result, $code);
    }

    public static function exception(
        \Throwable $exception,
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR
    ): ApiResponse {
        $result = ['success' => false];

        $result['error'] = "Ошибка ".$exception->getMessage()."\n В файле ".$exception->getFile()."\n строка ".$exception->getLine();

        return new self($result, $code);
    }
}
