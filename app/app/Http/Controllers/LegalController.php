<?php

namespace App\Http\Controllers;

use App\Exports\NewCompanyExport;
use App\Helpers\ApiResponse;
use App\Http\Requests\WidgetSettingsRequest;
use App\Mail\NewCompanyNotice;
use App\Models\Settings;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class LegalController extends Controller
{
    protected $company_data;
    protected $field_value;

    /**
     * @param WidgetSettingsRequest $request
     * @return ApiResponse
     */
    public function sendInfoToLegal(WidgetSettingsRequest $request)
    {
        $data = $request->all();
        $id_1c = $this->getCustomFieldValueById($data['custom_fields_values'], env('CF_ID_COMP'));
        // необходим id компании
        if ($id_1c->isNull()) {
            return ApiResponse::error('Необходим ID компании');
        }
        // необходим адрес почты юр.отдела
        $settings = Settings::query()->where(Settings::ACCOUNT_ID, $data['account_id'])->get()->toArray()[0];
        if (!$settings[Settings::LEGAL_EMAIL]) {
            return ApiResponse::error('Необходимо в настройках указать email');
        }
        // разбить строку с адресами
        $emails = explode(';', $settings[Settings::LEGAL_EMAIL]);

        $dir = 'company/' . date('Y-m-d') . '/';
        $filename = 'Карточка клиента ' . preg_replace('[\/]', '', $data['name']) . '.xlsx';
        $data_set = $this->prepareDataSet($data);

        if (Excel::store(new NewCompanyExport($data_set), $dir . $filename)) {
            // отправка письма
            $data['filepath'] = $dir . $filename;
            // отправка  письма на несколько адресов
            foreach ($emails as $email) {
                Mail::to(trim($email))->send(new NewCompanyNotice($data));
            }
            return ApiResponse::success(['data' => $data]);
        }
        return ApiResponse::error('Не удалось сформировать xlsx файл');
    }

    /**
     * @param array $fields
     * @param int $id
     * @return LegalController
     */
    protected function getCustomFieldValueById(array $fields, int $id): LegalController
    {
        $this->field_value = null;
        foreach ($fields as $field) {
            if ((int)$field['field_id'] === $id) {
                $this->field_value = $field['values'];
                break;
            }
        }
        return $this;
    }

    protected function get(string $mode = 'first')
    {
        if ($this->isNull()) {
            return '';
        }
        switch ($mode) {
            case 'first':
                $a = $this->field_value[0]['value'];
                break;
            case 'all':
                $a = '';
                $count = 0;
                foreach ($this->field_value as $value) {
                    if ($count > 0) {
                        $a .= ';';
                    }
                    $a .= $value['value'];
                    $count++;
                }
                break;
        }
        return $a;
    }

    protected function isNull()
    {
        return is_null($this->field_value);
    }

    protected function getCustomFieldValues(array $fields): ?array
    {
        $fields_values = [];

    }

    protected function validateCustomFields(array $data)
    {
        return false;
    }

    /**
     * @param array $data
     * @return array|null
     */
    protected function prepareDataSet(array $data): ?array
    {
        date_default_timezone_set('Europe/Moscow');
        $cf = $data['custom_fields_values'];
        $id_1c = $this->getCustomFieldValueById($cf, env('CF_ID_COMP'))->get();
        $compname = $data['name'];
        $compfullname = $this->getCustomFieldValueById($cf, env('CF_COMPFULLNAME'))->get();
        $manager = explode(" ", $data['responsible_user_name'])[0];
        $address = $this->getCustomFieldValueById($cf, env('CF_ADDRESS'))->get();
        $mainaddress = $this->getCustomFieldValueById($cf, env('CF_MAINADDRESS'))->get();
        $activity = $this->getCustomFieldValueById($cf, env('CF_ACTIVITY'))->get();
        $delivery = $this->getCustomFieldValueById($cf, env('CF_DELIVERY'))->get();
        $inn = $this->getCustomFieldValueById($cf, env('CF_INN'))->get();
        $kpp = $this->getCustomFieldValueById($cf, env('CF_KPP'))->get();
        $delivery_time_from = $this->getCustomFieldValueById($cf, env('CF_DELIVERY_TIME_FROM'))->get();
        if ($delivery_time_from !== '') {
            $delivery_time_from = date('H', $delivery_time_from);
        }
        $delivery_time_to = $this->getCustomFieldValueById($cf, env('CF_DELIVERY_TIME_TO'))->get();
        if ($delivery_time_to !== '') {
            $delivery_time_to = date('H', $delivery_time_to);
        }
        $addinfo = $this->getCustomFieldValueById($cf, env('CF_ADDINFO'))->get();
        $account = $this->getCustomFieldValueById($cf, env('CF_ACCOUNT'))->get();
        $bank = $this->getCustomFieldValueById($cf, env('CF_BANK'))->get();

        $phone = $this->getCustomFieldValueById($cf, env('CF_TEL'))->get('all');
        $email = $this->getCustomFieldValueById($cf, env('CF_EMAIL'))->get('all');

        $shipment_disallow_from = $this->getCustomFieldValueById($cf, env('CF_SHIPMENT_DISALLOW_FROM'))->get();
        $koordlati = $this->getCustomFieldValueById($cf, env('CF_KOORDLATI'))->get();
        $koordlong = $this->getCustomFieldValueById($cf, env('CF_KOORDLONG'))->get();

        return [
            ['', 'Наименование компании/ФИО индивидуального предпринимателя', $compfullname, $id_1c],
            ['', 'Сокращенное наименование компании/ФИО индивидуального предпринимателя', $compname, ''],
            ['', 'Менеджер ООО ТД "ЛФБ"', $manager, ''],
            ['', 'Вид деятельности контрагента', $activity, ''],
            ['', 'Адрес', $address, ''],
            ['', 'Фактический адрес', $mainaddress, ''],
            ['', 'Адрес доставки', $delivery, ''],
            ['', 'ИНН', $inn, ''],
            ['', 'КПП', $kpp, ''],
            ['', 'Время доставки (с - по)', $delivery_time_from, ''],
            ['', '', $delivery_time_to, ''],
            ['', 'Дополнительная информация  ', $addinfo, ''],
            ['', 'Номер счета', $account, ''],
            ['', 'Наименование банка', $bank, ''],
            ['', 'Корр. счет', '', ''],
            ['', 'БИК', '', ''],
            ['', 'Телефон', $phone, ''],
            ['', 'Электронная почта', $email, ''],
            ['', 'Запрещать отгрузку при сумме задолженности более', $shipment_disallow_from, ''],
            ['', 'Номер Guid  в системе Меркурий', '', ''],
            ['', 'Широта', $koordlati, ''],
            ['', 'Долгота', $koordlong, ''],
            ['', 'Тип цен', '', ''],
            ['', 'Минимальная сумма заказа', '', ''],
            ['', 'Направление', '', ''],
        ];
    }
}
