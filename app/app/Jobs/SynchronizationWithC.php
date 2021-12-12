<?php

namespace App\Jobs;

use AmoCRM\Client\AmoCRMApiClient;
use App\Helpers\AmoCRMHelper;
use App\Http\Controllers\WidgetController;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SynchronizationWithC implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    protected $customers;

    /**
     * Create a new job instance.
     *
     * @param $customers
     */
    public function __construct(array $customers)
    {
        $this->customers = $customers;
    }

    /**
     * Execute the job.
     *
     * @param AmoCRMApiClient $apiClient
     * @return void
     * @throws Exception
     */
    public function handle(AmoCRMApiClient $apiClient)
    {
        try {
            $result = (new AmoCRMHelper($apiClient, (int)env('AMOCRM_ACCOUNT_ID')))->createCompanies($this->customers);
            Log::info('Successfully creating/updating companies!');
            Log::info($result);
        } catch (Exception $e) {
            throw new Exception('Synchronization exception - ' . $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        Log::error(__METHOD__ . ' '. var_export($exception, true));
    }
}
