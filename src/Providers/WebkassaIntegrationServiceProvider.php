<?php

namespace webdophp\WebkassaIntegration\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use webdophp\WebkassaIntegration\Http\Middleware\CheckApiKey;
use webdophp\WebkassaIntegration\Services\WebkassaService;

class WebkassaIntegrationServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     */
    public function register(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        // Регистрируем middleware с псевдонимом 'webkassa.key'
        $router->aliasMiddleware('webkassa.key', CheckApiKey::class);

        $this->mergeConfigFrom(__DIR__.'/../../config/webkassa-integration.php', 'webkassa-integration');

        $this->app->singleton(WebkassaService::class, function () {
            return new WebkassaService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Публикация конфигов
        $this->publishes([
            __DIR__.'/../../config/webkassa-integration.php' => config_path('webkassa-integration.php'),
        ], 'webkassa-integration');

        // Публикация миграций
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'webkassa-integration');

        // Публикация вьюшек
        $this->publishes([
            __DIR__.'/../../resources/views/emails/webkassa' => resource_path('views/vendor/webkassa-integration'),
        ], 'webkassa-views');

        // Загрузка вьюшек
        $this->loadViewsFrom(__DIR__.'/../../resources/views/emails/webkassa', 'webkassa-integration');

        // Загрузка миграций
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Загрузка API маршрутов
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

    }

}