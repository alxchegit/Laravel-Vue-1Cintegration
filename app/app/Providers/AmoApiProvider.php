<?php

namespace App\Providers;

use AmoCRM\Client\AmoCRMApiClient;
use App\Helpers\AmoCRMHelper;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use function env;

class AmoApiProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(AmoCRMApiClient::class, function (Application $app) {
            return new AmoCRMApiClient(env('AMO_CLIENT_ID'), env('AMO_CLIENT_SECRET'), env('AMO_REDIRECT_URI'));
        });

        $this->app->singleton(AmoCRMHelper::class, function (Application $app) {
            return new AmoCRMHelper($app->make(AmoCRMApiClient::class));
        });
    }

}