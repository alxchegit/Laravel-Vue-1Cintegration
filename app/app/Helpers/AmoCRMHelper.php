<?php


namespace App\Helpers;


use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CompaniesCollection;
use AmoCRM\Collections\CustomFields\CustomFieldEnumsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\EntitiesServices\BaseEntity;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Exceptions\InvalidArgumentException;
use AmoCRM\Filters\BaseEntityFilter;
use AmoCRM\Filters\CompaniesFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\CustomFields\CustomFieldModel;
use AmoCRM\Models\CustomFields\EnumModel;
use AmoCRM\Models\CustomFieldsValues\BaseCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\CheckboxCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\DateCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\DateTimeCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\LegalEntityCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\MultiselectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\RadiobuttonCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\StreetAddressCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextareaCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\UrlCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\CheckboxCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateTimeCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\LegalEntityCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultiselectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\RadiobuttonCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\StreetAddressCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextareaCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\UrlCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\CheckboxCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\DateCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\DateTimeCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\LegalEntityCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultiselectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\RadiobuttonCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\StreetAdressCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextareaCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\UrlCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use App\Http\Controllers\WidgetController;
use App\Models\AmoCompany;
use App\Models\AmoLead;
use App\Models\AuthData;
use App\Models\Settings;
use App\Models\UsersMatch;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use function reset;

class AmoCRMHelper
{
    /**
     * @var AmoCRMApiClient
     */
    private $amoCrmClient;
    private $authorized = false;
    private $accountId = 0;


    /**
     * AmoCRMHelper constructor.
     * @param AmoCRMApiClient $amoCrmClient
     * @param int $accountId
     * @throws \Exception
     */
    public function __construct(AmoCRMApiClient $amoCrmClient, int $accountId = 0)
    {
        $this->amoCrmClient = $amoCrmClient;
        if ($accountId !== 0) {
            $this->authorize($accountId);
            $this->accountId = $accountId;
        }
    }

    /**
     * @param int $accountId
     * @return AmoCRMApiClient
     * @throws \Exception
     */
    public function authorize(int $accountId): AmoCRMApiClient
    {
        $auth = AuthData::query()->firstWhere('account_id', $accountId);
        if ($auth === null) {
            throw new \Exception('NOT AUTHORIZED');
        }
        $this->amoCrmClient->setAccountBaseDomain($auth->base_domain);
        $token = new AccessToken(
            [
                'baseDomain' => $auth->base_domain,
                'refresh_token' => $auth->refresh_token,
                'expires' => Carbon::createFromTimeString($auth->expires)->getTimestamp(),
                'access_token' => $auth->access_token
            ]
        );
        $this->amoCrmClient->setAccessToken($token)->onAccessTokenRefresh(
            function (AccessTokenInterface $accessToken, string $baseDomain) use ($auth) {
                $auth->update(
                    [
                        AuthData::REFRESH_TOKEN => $accessToken->getRefreshToken(),
                        AuthData::EXPIRES => Carbon::createFromTimestamp($accessToken->getExpires())->toDateTimeString(),
                        AuthData::ACCESS_TOKEN => $accessToken->getToken()
                    ]
                );
            }
        );
        $this->authorized = true;
        return $this->amoCrmClient;
    }

    /**
     * @param BaseCustomFieldValuesModel|null $customField
     * @return array|bool|int|mixed|string|null
     */
    public function getValueOfCustomField(?BaseCustomFieldValuesModel $customField)
    {
        if ($customField === null) {
            return null;
        }
        $fieldType = $customField->getFieldType();

        if (($fieldType === CustomFieldModel::TYPE_TEXT || $fieldType === CustomFieldModel::TYPE_NUMERIC) && $customField->getValues() !== null) {
            $val = $customField->getValues()->toArray();
            return reset($val) ? reset($val)['value'] : null;
        }
        if ($fieldType === CustomFieldModel::TYPE_SELECT) {
            /** @var SelectCustomFieldValuesModel $customField */
            return $customField->getValues()->current()->toArray()['enum_id'];
        }
        if ($fieldType === CustomFieldModel::TYPE_DATE) {
            /** @var DateCustomFieldValueModel $customField */
            return $customField->getValue();
        }
        return null;
    }

    /**
     * @param int $id
     * @return LeadModel|null
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function getLeadById(int $id): ?LeadModel
    {
        if ($this->authorized) {
            return $this->amoCrmClient->leads()->getOne($id, [EntityTypesInterface::COMPANIES]);
        }
        return null;
    }

    /**
     * @param int $id
     * @param array $with
     * @return CompanyModel|null
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function getCompanyById(int $id, array $with = []): ?CompanyModel
    {
        if ($this->authorized) {
            return $this->amoCrmClient->companies()->getOne($id, $with);
        }
        return null;
    }

    /**
     * @param int $leadId
     * @param string $text
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     * @throws InvalidArgumentException
     */
    public function addCommonNoteToLead(int $leadId, string $text): void
    {
        if ($this->authorized) {
            $notesCollection = new NotesCollection();
            $serviceMessageNote = new CommonNote();
            $serviceMessageNote->setEntityId($leadId)
                ->setText('Автоматизация БП "Выставление счета" : ' . $text);
            $notesCollection->add($serviceMessageNote);
            $leadNotesService = $this->amoCrmClient->notes(EntityTypesInterface::LEADS);
            $leadNotesService->add($notesCollection);
        }
    }

    /**
     * @param LeadModel $lead
     * @param int $fieldId
     * @param string $value
     * @throws AmoCRMApiException
     * @throws AmoCRMoAuthApiException
     * @throws InvalidArgumentException
     */
    public function setDateCustomFieldValue(LeadModel $lead, int $fieldId, string $value): void
    {
        $customFieldsValuesCollection = new CustomFieldsValuesCollection();
        $fieldModel = new DateCustomFieldValuesModel();
        $fieldModel->setFieldId($fieldId);
        $fieldModel = $fieldModel->setValues(
            (new DateCustomFieldValueCollection())->add((new DateCustomFieldValueModel())->setValue($value))
        );
        $customFieldsValuesCollection->add($fieldModel);
        $lead->setCustomFieldsValues($customFieldsValuesCollection);
        $this->amoCrmClient->leads()->updateOne($lead);
    }

    /**
     * Создание компаний в амо на основании массива данных
     * @param array $data
     * @return array|string
     * @throws Exception
     */
    public function createCompanies(array $data)
    {
        if(!is_array($data) || !count($data)) {
            Log::channel('amohandler')->error('Incoming data is not properly set');
            return false;
        }

        $updated_companies = 0;
        $added_companies = 0;
        $errors = [];
        foreach ($data as $key => $comp_data) {
            $companiesCollection = new CompaniesCollection();

            // создаем модель компании
            $company = new CompanyModel();
            // ID 1C
            if (!key_exists('id', $comp_data)) {
                // если нет id то точно пропускаем
                $errors[] = sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'id' is missed");
                continue;
            }
            // проверка на двойника
            $twin = $this->checkForTwin($comp_data['id']);
            // если есть двойник - назначаем id
            if ($twin['status']) {
                $company->setId((int)$twin['id']);
                $action = 'update';
            } else {
                $action = 'add';
            }

            // COMPNAME
            if (key_exists('compname', $comp_data)) {
                $company->setName($comp_data['compname']);
            }
            // MANAGER
            if (key_exists('manager', $comp_data)) {
                try {
                    $user = UsersMatch::query()
                        ->where(UsersMatch::ACCOUNT_ID, $this->accountId)
                        ->where(UsersMatch::C_USER_ID, $comp_data['manager']['id'])->firstOrFail()->toArray();
                } catch (ModelNotFoundException $e) {
                    $errors[] = sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'manager' - [" . $comp_data['manager']['id'] . "] is missed from DB");
                    continue;
                }

                if (count($user) && $this->checkForAmoUser($user[UsersMatch::AMO_USER_ID])) {
                    $company->setResponsibleUserId((int)$user[UsersMatch::AMO_USER_ID]);
                } else {
                    $errors[] = sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'manager' - [" . $comp_data['manager']['id'] . "] is missed in AMOCRM");
                    continue;
                }
            }

            // заполним кастомные поля
            $company->setCustomFieldsValues($this->fillCompanyCustomField($comp_data));
            $companiesCollection->add($company);
            try {
                if ($action === 'add') {
                    $this->amoCrmClient->companies()->add($companiesCollection);
                    $added_companies++;
                }
                if ($action === 'update') {
                    $this->amoCrmClient->companies()->update($companiesCollection);
                    $updated_companies++;
                }
            } catch (AmoCRMApiException $e) {
                if($e->getMessage() === 'Response has validation errors') {
                    $descr = method_exists($e, 'getValidationErrors') ? $e->getValidationErrors() : $e->getDescription();
                    $errors[] = sprintf('Request[%s] has validation errors - %s', $key, var_export($descr));
                } else {
                    $errors[] = sprintf('Request[%s] skipped - %s', $key, $e->getDescription());

                }
            }
        }

        Log::channel('amohandler')->info('Add companies summary:');
        Log::channel('amohandler')->info(var_export([
            'total_number_of_customers' => count($data),
            'added_customers' => $added_companies,
            'updated_customers' => $updated_companies,
            'errors' => $errors
        ], true));

        return true;
    }

    /**
     * @param array $comp_data
     * @return CustomFieldsValuesCollection
     */
    private function fillCompanyCustomField(array $comp_data): CustomFieldsValuesCollection
    {
        // новая коллекция полей для новой модели
        $customFieldsValuesCollection = new CustomFieldsValuesCollection();
        // ID_COMP
        $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::ID_COMP), $comp_data['id'], AmoCompany::getKey(AmoCompany::ID_COMP_FIELD_TYPE_ID));
        $customFieldsValuesCollection->add($fieldModel);
        // COMPFULLNAME
        if (key_exists('compfullname', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::COMPFULLNAME), $comp_data['compfullname'], AmoCompany::getKey(AmoCompany::COMPFULLNAME_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // MAINCOMPNAME
        if (key_exists('maincomp', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::MAINCOMPNAME), $comp_data['maincomp']['name'], AmoCompany::getKey(AmoCompany::MAINCOMPNAME_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // INN
        if (key_exists('inn', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::INN), $comp_data['inn'], AmoCompany::getKey(AmoCompany::INN_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // KPP
        if (key_exists('kpp', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::KPP), $comp_data['kpp'], AmoCompany::getKey(AmoCompany::KPP_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // DELIVERY_TIME_FROM
        if (key_exists('delivery_time_from', $comp_data)) {
            $time = explode('T', $comp_data['delivery_time_from']);
            $new_time = strtotime("2000-01-01T" . $time[1] . '+03:00');
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::DELIVERY_TIME_FROM), $new_time, AmoCompany::getKey(AmoCompany::DELIVERY_TIME_FROM_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // DELIVERY_TIME_TO
        if (key_exists('delivery_time_to', $comp_data)) {
            $time = explode('T', $comp_data['delivery_time_to']);
            $new_time = strtotime("2000-01-01T" . $time[1] . "+03:00");
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::DELIVERY_TIME_TO), $new_time, AmoCompany::getKey(AmoCompany::DELIVERY_TIME_TO_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // ADDINFO
        if (key_exists('addinfo', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::ADDINFO), $comp_data['addinfo'], AmoCompany::getKey(AmoCompany::ADDINFO_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // ROLE
        // в АМО всегда клиент
        $role_val = AmoCompany::getKey(AmoCompany::ROLE_ENUM_CLIENT);
        $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::ROLE), $role_val, AmoCompany::getKey(AmoCompany::ROLE_FIELD_TYPE_ID));
        $customFieldsValuesCollection->add($fieldModel);

        // COMPROLE
        if (key_exists('comprole', $comp_data)) {
            try {
                $field_id = AmoCompany::getKey(AmoCompany::COMPROLE);
                $val = $this->insertIfNewEnum(EntityTypesInterface::COMPANIES, $field_id, $comp_data['comprole']);
                if($val) {
                    $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::COMPROLE), $val, AmoCompany::getKey(AmoCompany::COMPROLE_FIELD_TYPE_ID));
                    $customFieldsValuesCollection->add($fieldModel);
                }
            } catch (Exception $e) {
                Log::debug(__METHOD__ . ' ' . $e->getMessage());
            }
        }

        // ACTIVITY
        if (key_exists('activity', $comp_data)) {
            try {
                $field_id = AmoCompany::getKey(AmoCompany::ACTIVITY);
                $val = $this->insertIfNewEnum(EntityTypesInterface::COMPANIES, $field_id, $comp_data['activity']);
                $fieldModel = $this->setFieldValue($field_id, $val, AmoCompany::getKey(AmoCompany::ACTIVITY_FIELD_TYPE_ID));
                $customFieldsValuesCollection->add($fieldModel);
            } catch (Exception $e) {
                Log::debug(__METHOD__ . ' ' . $e->getMessage());
            }
        }

        // UPD - список возможно не используемое
        if (key_exists('upd', $comp_data)) {
            if (gettype($comp_data['upd']) === 'string') {
                $upd_val = $comp_data['upd'];
            } elseif (gettype($comp_data['upd']) === 'boolean') {
                $upd_val = $comp_data['upd'] ? 'true' : 'false';
            } else {
                $upd_val = 'false';
            }
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::UPD), $upd_val, AmoCompany::getKey(AmoCompany::UPD_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // ACCESS_GROUP
        if (key_exists('access_group', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::ACCESS_GROUP), $comp_data['access_group'], AmoCompany::getKey(AmoCompany::ACCESS_GROUP_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }

        // KOORDLATI
        if (key_exists('koordlati', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::KOORDLATI), $comp_data['koordlati'], AmoCompany::getKey(AmoCompany::KOORDLATI_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // KOORDLONG
        if (key_exists('koordlong', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::KOORDLONG), $comp_data['koordlong'], AmoCompany::getKey(AmoCompany::KOORDLONG_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // DELIVERY
        if (key_exists('delivery', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::DELIVERY), $comp_data['delivery'], AmoCompany::getKey(AmoCompany::DELIVERY_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // ADDRESS
        if (key_exists('address', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::ADDRESS), $comp_data['address'], AmoCompany::getKey(AmoCompany::ADDRESS_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // MAINADDRESS
        if (key_exists('mainaddress', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::MAINADDRESS), $comp_data['mainaddress'], AmoCompany::getKey(AmoCompany::MAINADDRESS_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // SHIPMENT_DISALLOW_FROM
        if (key_exists('shipment_disallow_from', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::SHIPMENT_DISALLOW_FROM), $comp_data['shipment_disallow_from'], AmoCompany::getKey(AmoCompany::SHIPMENT_DISALLOW_FROM_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // SHIPMENT_DISALLOW
        if (key_exists('shipment_disallow', $comp_data)) {
            if (gettype($comp_data['shipment_disallow']) === 'string') {
                $upd_val = $comp_data['shipment_disallow'];
            } elseif (gettype($comp_data['shipment_disallow']) === 'boolean') {
                $upd_val = $comp_data['shipment_disallow'] ? 'true' : 'false';
            } else {
                $upd_val = 'false';
            }
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::SHIPMENT_DISALLOW), $upd_val, AmoCompany::getKey(AmoCompany::SHIPMENT_DISALLOW_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // TEL
        if (key_exists('tel', $comp_data)) {
            $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
            $phoneField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($comp_data['tel'])
                    )
            );
            $customFieldsValuesCollection->add($phoneField);
        }
        // EMAIL
        if (key_exists('email', $comp_data)) {
            $emailField = (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL');
            $emailField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($comp_data['email'])
                    )
            );
            $customFieldsValuesCollection->add($emailField);
        }
        // main_agreement
        if (key_exists('main_agreement', $comp_data)) {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::APPROVED_BY_LEGAL), 'true', AmoCompany::getKey(AmoCompany::APPROVED_BY_LEGAL_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        } else {
            $fieldModel = $this->setFieldValue(AmoCompany::getKey(AmoCompany::APPROVED_BY_LEGAL), 'false', AmoCompany::getKey(AmoCompany::APPROVED_BY_LEGAL_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // синхронизированно с 1С
        $customFieldsValuesCollection->add($this->setFieldValue(AmoCompany::getKey(AmoCompany::SYNCHRO_WITH_1C), 'true', AmoCompany::getKey(AmoCompany::SYNCHRO_WITH_1C_FIELD_TYPE_ID)));

        return $customFieldsValuesCollection;
    }
    /**
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     * @throws Exception
     *
     */
    public function updateLeadsFrom1CHandler(array $data)
    {
        try {
            $lead_service = $this->amoCrmClient->leads();
        } catch (AmoCRMMissedTokenException $e) {
            $msg = __METHOD__ . " Exception AmoCRMMissedTokenException - " . $e->getMessage() . ' - ' . $e->getDescription();
            Log::channel('amohandler')->error($msg);
            throw new Exception($msg);
        }

        if (!is_array($data)) {
            $msg = __METHOD__ . " Data must be of type - array. " . gettype($data) . ' are given';
            Log::channel('amohandler')->error($msg);
            throw new Exception($msg);
        }

        // коллекция для добавления новых сделок
        $leadsCollection_to_add = new LeadsCollection();
        // коллекция для обновления сделок
        $leadsCollection_to_update = new LeadsCollection();

        // подготовим массив пользователей
        $manager_ids = array_column($data, 'manager_id');
        $amo_managers_ids = UsersMatch::query()->where(UsersMatch::ACCOUNT_ID, (int)env('AMOCRM_ACCOUNT_ID'))->where(function ($query) use ($manager_ids) {
            foreach ($manager_ids as $id) {
                $query->orWhere(UsersMatch::C_USER_ID, $id);
            }
            return $query;
        })->get([UsersMatch::AMO_USER_ID, UsersMatch::C_USER_ID])->toArray();
        $amo_managers_ids = array_column($amo_managers_ids, UsersMatch::AMO_USER_ID, UsersMatch::C_USER_ID);
        // подготовим статусы и воронку
        try {
            $setts = Settings::query()->where(Settings::ACCOUNT_ID, $this->accountId)
                ->firstOrFail()->toArray();
            $a = explode('_', $setts[Settings::STATUSES]);
            $settings = [
                'pipeline_id' => (int)$a[0],
                'status_id' => (int)$a[1],
            ];
        } catch (ModelNotFoundException $e) {
            throw new Exception($e->getMessage() . ' Need to do settings for statuses');
        }

        foreach ($data as $key => $order) {
            if (!key_exists('order_guid', $order)) {
                Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'order_guid' is missing"));
                continue;
            }

            $no_leads = false;
            if (key_exists('lead_id', $order)) {
                $lead = $lead_service->getOne($order['lead_id']);
            } else {
                $filter = new LeadsFilter();
                $filter->setQuery($order['order_guid']);
                try {
                    $lead = $lead_service->get($filter)->first();
                } catch (AmoCRMoAuthApiException|AmoCRMApiException $e) {
                    if ($e->getMessage() === 'No content') {
                        $no_leads = true;
                    } else {
                        Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, $e->getMessage()));
                        continue;
                    }
                }
            }

            if(key_exists('manager_id', $order)) {
                $user_id = key_exists($order['manager_id'], $amo_managers_ids) ? $amo_managers_ids[$order['manager_id']] : 0;
                // не будем добавлять или обновлять сделку у которой юзер не определен в амо
                if(!$user_id || !$this->checkForAmoUser($user_id)) {
                    Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "user_id[$user_id]"));
                    continue;
                }
            } else {
                continue;
            }

            // не найдено - надо создавать новую сделку
            if ($no_leads) {
                // модель для новой сделки
                $new_lead = new LeadModel();
                // новая коллекция полей для новой модели
                $customFieldsValuesCollection = new CustomFieldsValuesCollection();
                //
                $new_lead->setPipelineId($settings['pipeline_id']);
                $new_lead->setStatusId($settings['status_id']);
                $new_lead->setResponsibleUserId($user_id);

                $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::ORDER_GUID), $order['order_guid'], AmoLead::getKey(AmoLead::ORDER_GUID_FIELD_TYPE_ID));
                $customFieldsValuesCollection->add($fieldModel);
                if (key_exists('order_number', $order)) {
                    $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::ORDER_NUMBER), $order['order_number'], AmoLead::getKey(AmoLead::ORDER_NUMBER_FIELD_TYPE_ID));
                    $customFieldsValuesCollection->add($fieldModel);
                } else {
                    Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'order_number' must be provided"));
                    continue;
                }
                if (key_exists('order_amount', $order) || key_exists('realization_amount', $order)) {
                    $price = key_exists('order_amount', $order) ? $order['order_amount'] : $order['realization_amount'];
                    $new_lead->setPrice((int)$price);
                    $val = number_format($price, 2, '.', ' ');
                    $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::PAYMENT_BUDGET), $val, AmoLead::getKey(AmoLead::PAYMENT_BUDGET_FIELD_TYPE_ID));
                    $customFieldsValuesCollection->add($fieldModel);
                } else {
                    Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'price' must be provided"));
                    continue;
                }

                if (key_exists('kontragent_id', $order)) {
                    $filter = new CompaniesFilter();
                    $filter->setQuery($order['kontragent_id']);
                    try {
                        $company = $this->amoCrmClient->companies()->get($filter);
                    } catch (AmoCRMApiException $e) {
                        if ($e->getMessage() === 'No content') {
                            Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "Failed to find company with guid - " . $order['kontragent_id']));
                        } else {
                            Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, $e->getMessage()));
                        }
                        continue;
                    }
                    $new_lead->setCompany(
                        (new CompanyModel())
                            ->setId($company->first()->getId())
                    );
                } else {
                    Log::channel('amohandler')->info(sprintf('%s Request[%s] skipped - %s', __METHOD__, $key, "'kontragent_id' must be provided"));
                    continue;
                }

                $customFieldsValuesCollection = $this->fillOptionalCustomFields($order, $customFieldsValuesCollection);

                $new_lead->setCustomFieldsValues($customFieldsValuesCollection);
                $leadsCollection_to_add->add($new_lead);

                // найдено, обновляем сделку
            } else {
                $customFieldsValuesCollection = $lead->getCustomFieldsValues();
                $lead->setResponsibleUserId($user_id);
                // сменим не существующих юзеров
                $updated_by = $lead->getUpdatedBy();
                $created_by = $lead->getCreatedBy();
                if(!$this->checkForAmoUser($updated_by)) {
                    $lead->setUpdatedBy(0);
                }
                if(!$this->checkForAmoUser($created_by)) {
                    $lead->setCreatedBy(0);
                }
                if (key_exists('order_amount', $order) || key_exists('realization_amount', $order)) {
                    $price = key_exists('order_amount', $order) ? $order['order_amount'] : $order['realization_amount'];
                    $lead->setPrice((int)$price);
                    $val = number_format($price, 2, '.', ' ');
                    $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::PAYMENT_BUDGET), $val, AmoLead::getKey(AmoLead::PAYMENT_BUDGET_FIELD_TYPE_ID));
                    $customFieldsValuesCollection->add($fieldModel);
                }

                $customFieldsValuesCollection = $this->fillOptionalCustomFields($order, $customFieldsValuesCollection);

                $lead->setCustomFieldsValues($customFieldsValuesCollection);
                $leadsCollection_to_update->add($lead);
            }

            // запушим изменения и добавления
            try {
                if (count($leadsCollection_to_add->toArray())) {
                    $lead_service->add($leadsCollection_to_add);
                    Log::channel('amohandler')->info(__METHOD__ . ' Created - ' . count($leadsCollection_to_add->toArray()) );
                }
                if (count($leadsCollection_to_update->toArray())) {
                    $lead_service->update($leadsCollection_to_update);
                    Log::channel('amohandler')->info(__METHOD__ . ' Updated - ' .  count($leadsCollection_to_update->toArray()));
                }
                $leadsCollection_to_add = new LeadsCollection();
                $leadsCollection_to_update = new LeadsCollection();
            } catch (AmoCRMApiException $e) {
                $msg = __METHOD__ . " Exception while lead service handle - " . $e->getMessage() . "(" . var_export($e->getValidationErrors(), true) . ")";
                throw new Exception($msg);
            }

        }
        return true;


    }

    /**
     * @param int $field_id
     * @param $value
     * @param int $field_type_id
     * @return BaseCustomFieldValuesModel
     */
    public function setFieldValue(int $field_id, $value, int $field_type_id): BaseCustomFieldValuesModel
    {
        $fieldModel = $this->getFieldModel($field_type_id);
        // укажем id поля
        $fieldModel->setFieldId($field_id);
        // запишем значение в поля в зависимости от типа поля в АМО
        try {
            $fieldModel = $this->setFieldValueByType($fieldModel, $value, $field_type_id);
        } catch (Exception $e) {
            Log::debug(__METHOD__ . ' ' . $e->getMessage());
        }
        return $fieldModel;
    }


    /**
     * Запись в поле
     * @param BaseCustomFieldValuesModel $fieldModel - модель поля в который писать
     * @param $value
     * @param int $target_field_type
     * @return BaseCustomFieldValuesModel
     * @throws Exception
     */
    public function setFieldValueByType(BaseCustomFieldValuesModel $fieldModel, $value, int $target_field_type): BaseCustomFieldValuesModel
    {

        switch ((int)$target_field_type) {
            case 1:
                return $fieldModel->setValues((new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())->setValue((string)$value)));
                break;
            case 2:
                return $fieldModel->setValues((new NumericCustomFieldValueCollection())
                    ->add(
                        (new NumericCustomFieldValueModel())->setValue($value)));
                break;
            case 3:
                return $fieldModel->setValues((new CheckboxCustomFieldValueCollection())
                    ->add(
                        (new CheckboxCustomFieldValueModel())->setValue((string)$value === 'true')));
                break;
            case 4:
                return $fieldModel->setValues((new SelectCustomFieldValueCollection())
                    ->add(
                        (new SelectCustomFieldValueModel())
                            ->setEnumId((int)$value)
                    )
                );
                break;
            case 5:
                $collection = new MultiselectCustomFieldValueCollection;
                foreach ($value as $some_value) {
                    $collection = $collection->add(
                        (new MultiselectCustomFieldValueModel())
                            ->setEnumId((int)$some_value)
                    );
                }
                return $fieldModel->setValues($collection);
                break;
            case 6:
            case 14:
                return $fieldModel->setValues((new DateCustomFieldValueCollection())
                    ->add(
                        (new DateCustomFieldValueModel())->setValue($value)));
                break;
            case 7:
                return $fieldModel->setValues((new UrlCustomFieldValueCollection())
                    ->add(
                        (new UrlCustomFieldValueModel())->setValue($value)));
                break;
            case 8:
                return $fieldModel->setValues((new MultitextCustomFieldValueCollection())
                    ->add(
                        (new MultitextCustomFieldValueModel())
                            ->setEnumId($value)));
                break;
            case 9:
                return $fieldModel->setValues((new TextareaCustomFieldValueCollection())
                    ->add(
                        (new TextareaCustomFieldValueModel())->setValue((string)$value)));
                break;
            case 10:
                return $fieldModel->setValues((new RadiobuttonCustomFieldValueCollection())
                    ->add(
                        (new RadiobuttonCustomFieldValueModel())
                            ->setEnumId($value)
                    )
                );
                break;
            case 11:
                return $fieldModel->setValues((new StreetAddressCustomFieldValueCollection())
                    ->add(
                        (new StreetAdressCustomFieldValueModel())->setValue($value)));
                break;
            case 19:
                return $fieldModel->setValues((new DateTimeCustomFieldValueCollection())
                    ->add(
                        (new DateTimeCustomFieldValueModel())->setValue($value)));
                break;
            case 15:
                $collection = new LegalEntityCustomFieldValueCollection;

                $collection->add(
                    (new LegalEntityCustomFieldValueModel)
                        ->setValue($value)
                );

                return $fieldModel->setValues($collection);
                break;
            default:
                throw new Exception("Field type = " . $target_field_type . " doesn\`t support yet!");
        }
    }

    /**
     * Создать модель поля в зависимости от его id
     * @param int $field_type_id - id поля в амосрм
     * @return BaseCustomFieldValuesModel
     */
    public function getFieldModel(int $field_type_id)
    {
        switch ($field_type_id) {
            case 1:
                return new TextCustomFieldValuesModel;
                break;
            case 2:
                return new NumericCustomFieldValuesModel;
                break;
            case 3:
                return new CheckboxCustomFieldValuesModel;
                break;
            case 4:
                return new SelectCustomFieldValuesModel;
                break;
            case 5:
                return new MultiselectCustomFieldValuesModel;
                break;
            case 6:
            case 14:
                return new DateCustomFieldValuesModel;
                break;
            case 7:
                return new UrlCustomFieldValuesModel;
                break;
            case 8:
                return new MultitextCustomFieldValuesModel;
                break;
            case 9:
                return new TextareaCustomFieldValuesModel;
                break;
            case 10:
                return new RadiobuttonCustomFieldValuesModel;
                break;
            case 11:
                return new StreetAddressCustomFieldValuesModel;
                break;
            case 19:
                return new DateTimeCustomFieldValuesModel;
                break;
            case 15:
                return new LegalEntityCustomFieldValuesModel;
                break;

            default:
                return new BaseCustomFieldValuesModel;
        }
    }

    /**
     * @param array $data
     * @param CustomFieldsValuesCollection $customFieldsValuesCollection
     * @return CustomFieldsValuesCollection
     * @throws InvalidArgumentException
     */
    public function fillOptionalCustomFields(array $data, CustomFieldsValuesCollection $customFieldsValuesCollection)
    {
        if (key_exists('order_guid', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::ORDER_GUID), $data['order_guid'], AmoLead::getKey(AmoLead::ORDER_GUID_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('order_number', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::ORDER_NUMBER), $data['order_number'], AmoLead::getKey(AmoLead::ORDER_NUMBER_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('realization_guid', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::REALIZATION_GUID), $data['realization_guid'], AmoLead::getKey(AmoLead::REALIZATION_GUID_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('realization_number', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::REALIZATION_NUMBER), $data['realization_number'], AmoLead::getKey(AmoLead::REALIZATION_NUMBER_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        // оплата
        if (key_exists('payment_budget', $data)) {
            $val = number_format($data['payment_budget'], 2, '.', ' ');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::PAYMENT_BUDGET), $val, AmoLead::getKey(AmoLead::PAYMENT_BUDGET_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('payment_status', $data)) {
            $val = AmoLead::getKey(AmoLead::PAYMENT_STATUS_ENUM_PAID); // по умолчанию оплачен
            if (trim($data['payment_status']) === 'not paid') {
                $val = AmoLead::getKey(AmoLead::PAYMENT_STATUS_ENUM_NOT_PAID);
            }
            if (trim($data['payment_status']) === 'partial payment') {
                $val = AmoLead::getKey(AmoLead::PAYMENT_STATUS_ENUM_PARTIAL_PAID);
            }
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::PAYMENT_STATUS), $val, AmoLead::getKey(AmoLead::PAYMENT_STATUS_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('payment_amount', $data)) {
            $val = number_format($data['payment_amount'], 2, '.', ' ');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::PAYMENT_AMOUNT), $val, AmoLead::getKey(AmoLead::PAYMENT_AMOUNT_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }

        // курьер
        if (key_exists('courier_surname', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_SURNAME), $data['courier_surname'], AmoLead::getKey(AmoLead::COURIER_SURNAME_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_name', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_NAME), $data['courier_name'], AmoLead::getKey(AmoLead::COURIER_NAME_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_otchestvo', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_OTCHESTVO), $data['courier_otchestvo'], AmoLead::getKey(AmoLead::COURIER_OTCHESTVO_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_phone', $data)) {
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_PHONE), $data['courier_phone'], AmoLead::getKey(AmoLead::COURIER_PHONE_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_arrival_plan', $data)) {
            $new_time = strtotime($data['courier_arrival_plan'] . '+03:00');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_ARRIVAL_PLAN), $new_time, AmoLead::getKey(AmoLead::COURIER_ARRIVAL_PLAN_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_arrival_fact', $data)) {
            $new_time = strtotime($data['courier_arrival_fact'] . '+03:00');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_ARRIVAL_FACT), $new_time, AmoLead::getKey(AmoLead::COURIER_ARRIVAL_FACT_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_departure_plan', $data)) {
            $new_time = strtotime($data['courier_departure_plan'] . '+03:00');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_DEPARTURE_PLAN), $new_time, AmoLead::getKey(AmoLead::COURIER_DEPARTURE_PLAN_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }
        if (key_exists('courier_departure_fact', $data)) {
            $new_time = strtotime($data['courier_departure_fact'] . '+03:00');
            $fieldModel = $this->setFieldValue(AmoLead::getKey(AmoLead::COURIER_DEPARTURE_FACT), $new_time, AmoLead::getKey(AmoLead::COURIER_DEPARTURE_FACT_FIELD_TYPE_ID));
            $customFieldsValuesCollection->add($fieldModel);
        }

        return $customFieldsValuesCollection;
    }

    /**
     * проверим существование компании по фильтру
     * @param string $id
     * @return array
     */
    private function checkForTwin(string $id): array
    {
        $filter = new CompaniesFilter();
        $filter->setQuery($id);
        try {
            //Получим компании по фильтру
            $service = $this->amoCrmClient->companies();

            $result = $service->get($filter)->toArray();
            if (count($result) === 0) {
                return [
                    'status' => false
                ];
            } else {
                return [
                    'status' => true,
                    'id' => $result[0]['id']
                ];
            }
        } catch (\Throwable $e) {
            return [
                'status' => false
            ];
        }
    }


    /**
     * Добавление полей в селект
     * @param string $entityType
     * @param int $field_id
     * @param string $val
     * @param bool $enum_id
     * @return int|null
     * @throws \Exception
     */
    private function insertIfNewEnum(string $entityType, int $field_id, string $val, bool $enum_id = false): int
    {
        $apiClient = $this->amoCrmClient;

        try {
            $customFieldsService = $apiClient->customFields($entityType);
            $target_cf = $customFieldsService->getOne($field_id);
            // если нет такого поля вообще
            if($target_cf === null) {
                throw new \Exception('No such field, with id - ' . $field_id);
            }
            /** @var CustomFieldEnumsCollection $enums_row */
            $enums_row = $target_cf->getEnums();
            $sort = max(array_column($enums_row->toArray(), 'sort'));
            $enum_val = $enums_row->getBy('value', $val);
            // если нет такого поля - добавим
            if($enum_val === null) {
                $enums_row->add(
                    (new EnumModel())
                        ->setValue($val)
                        // Обязательно указать сорт
                        ->setSort($sort + 10)
                );
                $target_cf->setEnums($enums_row);
                $target_cf = $customFieldsService->updateOne($target_cf);
                return $target_cf->getEnums()->getBy('value', $val)->getId();
            } else {
                return $enum_val->getId();
            }

        } catch (AmoCRMMissedTokenException | InvalidArgumentException | AmoCRMoAuthApiException | AmoCRMApiException $e) {
            throw new \Exception(__LINE__ . $e->getMessage());
        }
    }

    /**
     * проверка наличия юзера в амо
     * @param int $id
     * @return bool
     */
    private function checkForAmoUser(int $id): bool
    {
        $apiClient = $this->amoCrmClient;
        try {
            $usersService = $apiClient->users();
            $user = $usersService->getOne($id);
            return $user->getRights()->getIsActive();
        } catch (\Exception $e) {
            return false;
        }
    }

}
