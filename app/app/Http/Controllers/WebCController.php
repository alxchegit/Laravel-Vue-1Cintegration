<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use App\Helpers\AmoCRMHelper;
use App\Helpers\ApiResponse;
use App\Helpers\WebCService;
use App\Http\Requests\WidgetSettingsRequest;
use App\Jobs\UpdateLeadsFrom1CJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebCController extends Controller
{

    public function listPrintForms(WidgetSettingsRequest $request)
    {
        return WebCService::get('/list_print_forms');
    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function invoice(WidgetSettingsRequest $request)
    {
        return WebCService::get('/invoice', $request->all());
    }

    /**
     * @param WidgetSettingsRequest $request
     * @param AmoCRMApiClient $amoApiClient
     * @return ApiResponse
     */
    public function realizationFulfillment(WidgetSettingsRequest $request, AmoCRMApiClient $amoApiClient)
    {
        $data = $request->all();

        $result = WebCService::get('/order_fulfillment', $data);

        $clear = $result->getData(true);
        if(!$clear['success']) {
            return $result;
        }

        if($clear['payload']['status'] === 'success') {
            $realization_guid = $clear['payload']['response']['presets']['id'];
            $realization_number = $clear['payload']['response']['presets']['numberbill'];

            $amo_data = [
                'order_guid' => $data['id'],
                'realization_guid' => $realization_guid,
                'realization_number' => $realization_number
            ];
            if(key_exists('lead_id', $data)) {
                $amo_data['lead_id'] = $data['lead_id'];
            }
            try {
                UpdateLeadsFrom1CJob::dispatch([$amo_data]);
//                (new AmoCRMHelper($amoApiClient, (int)env('AMOCRM_ACCOUNT_ID')))->updateLeadsFrom1CHandler([$amo_data]);
            } catch (Exception $e) {
            }
        }
        return $result;

    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getOrder(WidgetSettingsRequest $request)
    {
        $data = $request->all();

        return WebCService::get('/order', $data);
    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function sendOrder(WidgetSettingsRequest $request)
    {
        $data = $request->all();

        return WebCService::post('/order', $data);
    }
    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getProductInfo(WidgetSettingsRequest $request)
    {
        return WebCService::get('/info_by_product', [
            'product' => $request->get('prod_id'),
            'storage' =>    $request->get('storage'),
            'agreement' =>  $request->get('agreement')
        ]);
    }

    public function getProductsByParam(WidgetSettingsRequest $request)
    {
        $data =[];
        if($request->get('prod_id')) {
            $data['id_group'] = $request->get('prod_id');
        }
        if($request->get('name')) {
            $data['name'] = $request->get('name');
        }
        return WebCService::get('/products',$data);
    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getProductGroups(WidgetSettingsRequest $request)
    {
        return WebCService::get('/product_groups');
    }

    /**
     * @param Request|null $request
     * @return ApiResponse
     */
    public function getManagers(Request $request = null)
    {
        return WebCService::get('/managers');
    }

    /**
     * @param Request|null $request
     * @return ApiResponse
     */
    public function getCustomers(Request $request = null)
    {
        return WebCService::get('/customers');
    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function idRequest(WidgetSettingsRequest $request)
    {
        return WebCService::put('/customers', ['inn' => $request->get('inn')]);
    }

    /**
     * Получить информацию из 1С по id контрагента
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getDataByCompanyId(WidgetSettingsRequest $request)
    {
        // получаем данные из 1с по id компании
        // прерываемся при первой неудаче
        try {
            $gruzopoluchatel    = $this->clearCResponse(WebCService::get('/delivery_points', ['id' => $request->get('comp_id')]));
            $sklad              = $this->clearCResponse(WebCService::get('/storages', ['id' => $request->get('comp_id')]));
            $agreements         = $this->clearCResponse(WebCService::get('/agreements', ['id' => $request->get('comp_id')]));
            $dogovor            = $this->clearCResponse(WebCService::get('/contracts', ['id' => $request->get('comp_id')]));
            $yurcomp            = $this->clearCResponse(WebCService::get('/organizations'));
            $sposdost           = $this->clearCResponse(WebCService::get('/shipping_methods'));
            return ApiResponse::success([
                'gruzopoluchatel' => $gruzopoluchatel,
                'sklad'         => $sklad,
                'agreements'    => $agreements,
                'yurcomp'       => $yurcomp,
                'dogovor'       => $dogovor,
                'sposdost'      => $sposdost
            ]);
        } catch(Exception $e) {
            Log::error(__METHOD__ . ' Exception - ' . $e->getMessage());
            return ApiResponse::error('Ошибка при получении данных из 1С');
        }

    }

    /**
     * Получим полезную нагрузку из запросов к 1С
     * @param ApiResponse $response
     * @return array
     * @throws Exception
     */
    private function clearCResponse(ApiResponse $response): array
    {
        $data = $response->getData(true);
        if(!$data['success']) {
            throw new Exception($data['error']['body']);
        } else {
            if($data['payload']['status'] === 'error') {
                throw new Exception($data['payload']['response']['message']);
            }
            return $data['payload']['response'];
        }
    }
}
