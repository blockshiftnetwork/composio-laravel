<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Feature;

use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\ComposioServiceProvider;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager;
use Orchestra\Testbench\TestCase;

class ComposioServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ComposioServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('services.composio.api_key', 'test-api-key');
        $app['config']->set('services.composio.base_url', 'https://test.composio.dev');
        $app['config']->set('services.composio.default_user_id', 'test-user');
        $app['config']->set('services.composio.default_entity_id', 'test-entity');
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
        $this->assertEquals('test-api-key', config('services.composio.api_key'));
        $this->assertEquals('https://test.composio.dev', config('services.composio.base_url'));
        $this->assertEquals('test-user', config('services.composio.default_user_id'));
        $this->assertEquals('test-entity', config('services.composio.default_entity_id'));
    }

    public function test_facade_resolves_managers(): void
    {
        $this->assertInstanceOf(ConnectedAccountManager::class, Composio::connectedAccounts());
        $this->assertInstanceOf(AuthConfigManager::class, Composio::authConfigs());
        $this->assertInstanceOf(ToolkitManager::class, Composio::toolkits());
        $this->assertInstanceOf(TriggerManager::class, Composio::triggers());
        $this->assertInstanceOf(McpServerManager::class, Composio::mcp());
        $this->assertInstanceOf(FileManager::class, Composio::files());
        $this->assertInstanceOf(CustomToolRegistry::class, Composio::customTools());
    }

    public function test_managers_are_cached_within_a_manager_instance(): void
    {
        $manager = $this->app->make(ComposioManager::class);

        $this->assertSame($manager->toolkits(), $manager->toolkits());
        $this->assertSame($manager->triggers(), $manager->triggers());
        $this->assertSame($manager->mcp(), $manager->mcp());
        $this->assertSame($manager->files(), $manager->files());
        $this->assertSame($manager->customTools(), $manager->customTools());
    }

    public function test_tool_set_inherits_custom_tools_registry(): void
    {
        $manager = $this->app->make(ComposioManager::class);
        $manager->customTools()->register('LOCAL_PING', 'ping', [], fn () => 'pong');

        $toolSet = $manager->toolSet();
        $registry = $toolSet->customTools();

        $this->assertNotNull($registry);
        $this->assertTrue($registry->has('LOCAL_PING'));
    }
}
