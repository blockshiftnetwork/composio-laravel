<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class ComposioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/composio.php', 'composio');

        $this->app->singleton(Configuration::class, function () {
            return Configuration::getDefaultConfiguration()
                ->setApiKey('x-api-key', config('composio.api_key'))
                ->setHost(config('composio.base_url'));
        });

        $this->app->singleton(ComposioManager::class, function ($app) {
            return new ComposioManager(
                $app->make(Configuration::class),
                $app->bound(ClientInterface::class)
                    ? $app->make(ClientInterface::class)
                    : new Client,
            );
        });

        $this->app->bind(ComposioToolSet::class, function ($app) {
            return $app->make(ComposioManager::class)->toolSet(
                config('composio.default_user_id'),
                config('composio.default_entity_id'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/composio.php' => config_path('composio.php'),
        ], 'composio-config');
    }
}
