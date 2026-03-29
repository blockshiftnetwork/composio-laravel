<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Feature;

use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\ComposioServiceProvider;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;
use Orchestra\Testbench\TestCase;

class ComposioServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ComposioServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('composio.api_key', 'test-api-key');
        $app['config']->set('composio.base_url', 'https://test.composio.dev');
        $app['config']->set('composio.default_user_id', 'test-user');
        $app['config']->set('composio.default_entity_id', 'test-entity');
    }

    public function test_registers_configuration_singleton(): void
    {
        $config = $this->app->make(Configuration::class);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertEquals('https://test.composio.dev', $config->getHost());
    }

    public function test_registers_composio_manager_singleton(): void
    {
        $manager1 = $this->app->make(ComposioManager::class);
        $manager2 = $this->app->make(ComposioManager::class);

        $this->assertInstanceOf(ComposioManager::class, $manager1);
        $this->assertSame($manager1, $manager2);
    }

    public function test_binds_composio_tool_set(): void
    {
        $toolSet = $this->app->make(ComposioToolSet::class);

        $this->assertInstanceOf(ComposioToolSet::class, $toolSet);
    }

    public function test_config_is_merged(): void
    {
        $this->assertEquals('test-api-key', config('composio.api_key'));
        $this->assertEquals('https://test.composio.dev', config('composio.base_url'));
        $this->assertEquals('test-user', config('composio.default_user_id'));
        $this->assertEquals('test-entity', config('composio.default_entity_id'));
    }
}
