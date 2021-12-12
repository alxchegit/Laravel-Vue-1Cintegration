<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\WidgetSettingsRequest;
use App\Jobs\SynchronizationWithC;
use App\Models\Settings;
use App\Models\UsersMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getFieldIds(WidgetSettingsRequest $request): ApiResponse
    {
        return ApiResponse::success(['fieldIds' => [
            'id_1c'                         => +env('CF_ID_COMP'),
            'legal_confirm'                 => +env('CF_APPROVED_BY_LEGAL'),
            'inn'                           => +env('CF_INN'),
            'order_guid_lead_field'         => +env('CF_ORDER_GUID'),
            'order_number_lead_field'       => +env('CF_ORDER_NUMBER'),
            'realization_guid_lead_field'   => +env('CF_REALIZATION_GUID'),
            'realization_number_lead_field' => +env('CF_REALIZATION_NUMBER'),
            'full_name'                     => +env('CF_COMPFULLNAME'), // textarea
            'legal_request_data_time'       => +env('CF_LEGAL_APPROVAL_SEND'), // date time
            'payment_budget'                => +env('CF_PAYMENT_BUDGET'),
            'kpp'                           => +env('CF_KPP'),
            'adress'                        => +env('CF_ADDRESS'),
            'mainaddress'                   => +env('CF_MAINADDRESS'),
            'delivery'                      => +env('CF_DELIVERY'),
            'phone'                         => +env('CF_TEL'),
            'email'                         => +env('CF_EMAIL'),
            'bank'                          => +env('CF_BANK'),
            'activity'                      => +env('CF_ACTIVITY'),
            'delivery_time_from'            => +env("CF_DELIVERY_TIME_FROM"),
            'delivery_time_to'              => +env('CF_DELIVERY_TIME_TO'),
            'account'                       => +env('CF_ACCOUNT'),
            'comprole'                      => +env('CF_COMPROLE'),
        ]]);
    }

    /**
     * @param Request $request
     * @return ApiResponse
     */
    public function saveUsersMatch(Request $request)
    {
        $data = $request->all();

        if (!$data['account_id'] || !$data['match_users']) {
            return ApiResponse::error(['msg' => 'Not enough data']);
        }

        $inserting = [];
        foreach ($data['match_users'] as $values) {
            array_push($inserting, [
                UsersMatch::ACCOUNT_ID => $data['account_id'],
                UsersMatch::AMO_USER_ID => (int)$values['amo_id'],
                UsersMatch::C_USER_ID => $values['c_user_id']
            ]);
        }
        try {
            $deletedRows = UsersMatch::where(UsersMatch::ACCOUNT_ID, $data['account_id'])->delete();
            $users_match = DB::table('users_match')->insert($inserting);

            return ApiResponse::success(['msg' => 'Successfully inserted']);
        } catch (\Throwable $th) {
            return ApiResponse::exception($th);
        }

    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function getSettings(WidgetSettingsRequest $request): ApiResponse
    {
        $data = $request->all();

        try {
            $response['users_match'] = UsersMatch::query()
                ->where(UsersMatch::ACCOUNT_ID, $data['account_id'])
                ->get()->toArray();
            $response['settings'] = Settings::query()
                ->where(Settings::ACCOUNT_ID, $data['account_id'])
                ->get()->toArray();
            return ApiResponse::success(['response' => $response]);
        } catch (\Exception $e) {
            return ApiResponse::error(['message' => $e->getMessage()]);
        }
    }

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function saveSettings(WidgetSettingsRequest $request): ApiResponse
    {
        $data = $request->all();

        try {
            $settings = Settings::query()->firstOrNew(
                [Settings::ACCOUNT_ID => $data['account_id']]
            );

            if (key_exists('status_check', $data)) {
                $settings->setAttribute(Settings::STATUS_CHECK, $data['status_check'] === 'true');
            }
            if (key_exists('statuses', $data)) {
                $settings->setAttribute(Settings::STATUSES, (string)$data['statuses']);
            }
            if (key_exists('legal_email', $data)) {
                $settings->setAttribute(Settings::LEGAL_EMAIL, $data['legal_email']);
            }

            $settings->setAttribute(Settings::ACCOUNT_ID, $data['account_id']);
            if ($settings->save()) {
                return ApiResponse::success($settings);
            }
        } catch (\Throwable $th) {
            return ApiResponse::error($th->getMessage());
        }
    }

    /**
     * Запрос на загрузку партнеров из 1С и создание компаний в Амо
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function customersSync(WidgetSettingsRequest $request): ApiResponse
    {
        $data = $request->all();

        Log::info('User id:' . $data['managers_id'] . ' perform a synchronization');

        $customers = (new WebCController())->getCustomers()->getData(true)['payload']['response'];

        SynchronizationWithC::dispatch($customers);

        return ApiResponse::success(['response' => 'Sending to queue']);
    }

}
