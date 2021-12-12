<?php

namespace App\Jobs;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Exceptions\InvalidArgumentException;
use AmoCRM\Filters\CompaniesFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\LeadModel;
use App\Helpers\AmoCRMHelper;
use App\Helpers\ApiResponse;
use App\Http\Controllers\WidgetController;
use App\Models\AmoLead;
use App\Models\Settings;
use App\Models\UsersMatch;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateLeadsFrom1CJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;
    /**
     * @var AmoCRMApiClient
     */
    private $amoCrmApiClient;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @param AmoCRMApiClient $amoCrmApiClient
     * @return void
     */
    public function handle( AmoCRMApiClient $amoCrmApiClient )
    {
        try{
            (new AmoCRMHelper($amoCrmApiClient, (int)env('AMOCRM_ACCOUNT_ID')))->updateLeadsFrom1CHandler($this->data);
        } catch(Exception $e) {
            Log::error(__METHOD__ . ' Exception - ' . $e->getMessage());
        }
    }
}
