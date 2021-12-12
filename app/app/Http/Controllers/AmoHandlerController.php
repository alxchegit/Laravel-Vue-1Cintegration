<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\InvalidArgumentException;
use App\Helpers\AmoCRMHelper;
use App\Helpers\ApiResponse;
use App\Http\Requests\AmoHandlerRequest;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AmoHandlerController extends Controller
{

    /**
     * @param AmoHandlerRequest $request
     * @param AmoCRMApiClient $amoCRMApiClient
     * @return ApiResponse
     */
    public function updateOrCreateNewOrders(AmoHandlerRequest $request, AmoCRMApiClient $amoCRMApiClient): ApiResponse
    {
        $data = json_decode($request->getContent(), true);

        Log::channel('amohandler')->info(__METHOD__ . ' Incoming data for leads update');
        Log::channel('amohandler')->debug(var_export($data, true));

        try {
            (new AmoCRMHelper($amoCRMApiClient, (int)env('AMOCRM_ACCOUNT_ID')))->updateLeadsFrom1CHandler($data);
            return ApiResponse::success('ok!');
        } catch (Exception $e) {
            Log::channel('amohandler')->error($e->getMessage());
            return ApiResponse::error($e->getMessage(), Response::HTTP_OK);
        }
    }

    /**
     * Добавление компании из 1С в амо
     * @param AmoHandlerRequest $request
     * @param AmoCRMApiClient $amoCRMApiClient
     * @return ApiResponse
     */
    public function addCompany(AmoHandlerRequest $request, AmoCRMApiClient $amoCRMApiClient): ApiResponse
    {
        $data = json_decode($request->getContent(), true);

        Log::channel('amohandler')->debug(__METHOD__ . ' Incoming data for companies add');
        Log::channel('amohandler')->debug(var_export($data, true));

        try {
            (new AmoCRMHelper($amoCRMApiClient, (int)env('AMOCRM_ACCOUNT_ID')))->createCompanies($data);
            return ApiResponse::success('ok!');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_OK);
        }

    }
}
