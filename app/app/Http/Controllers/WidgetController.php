<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    /**
     * @var AmoCRMApiClient
     */
    private $amoCrmApiClient;

    public function __construct(AmoCRMApiClient $apiClient)
    {
//        $this->amoCrmApiClient = (new AmoCRMHelper($apiClient))->authorize(+env('AMOCRM_ACCOUNT_ID'));
    }

    public function test(Request $request)
    {
        exit("Hello TD LFB!");
    }

}
