<?php

namespace webdophp\WebkassaIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class WebkassaIntegrationServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     */
    public function register(): void
    {

        $this->mergeConfigFrom(__DIR__.'/../../config/webkassa-integration.php', 'webkassa-integration.php');

        $this->app->singleton(WebkassaService::class, function () {
            return new WebkassaService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/webkassa-integration.php' => config_path('webkassa-integration.php'),
        ], 'webkassa-integration');


        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'webkassa-integration');

        $this->publishes([
            __DIR__.'/../../resources/views/emails/webkassa' => resource_path('views/vendor/webkassa-integration'),
        ], 'webkassa-views');

        $this->loadViewsFrom(__DIR__.'/../../resources/views/emails/webkassa', 'webkassa-integration');


        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    }

}