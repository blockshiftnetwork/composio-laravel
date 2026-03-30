<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class ComposioServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app['config']->set('services.composio', array_merge([
            'api_key' => env('COMPOSIO_API_KEY'),
            'base_url' => env('COMPOSIO_BASE_URL', 'https://backend.composio.dev'),
            'default_user_id' => env('COMPOSIO_DEFAULT_USER_ID'),
            'default_entity_id' => env('COMPOSIO_DEFAULT_ENTITY_ID'),
        ], $this->app['config']->get('services.composio', [])));

        $this->app->singleton(Configuration::class, fn () => Configuration::getDefaultConfiguration()
            ->setApiKey('x-api-key', config('services.composio.api_key'))
            ->setHost(config('services.composio.base_url')));

        $this->app->singleton(ComposioManager::class, fn ($app): ComposioManager => new ComposioManager(
            $app->make(Configuration::class),
            $app->bound(ClientInterface::class)
                ? $app->make(ClientInterface::class)
                : new Client,
        ));

        $this->app->bind(ComposioToolSet::class, fn ($app) => $app->make(ComposioManager::class)->toolSet(
            config('services.composio.default_user_id'),
            config('services.composio.default_entity_id'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
