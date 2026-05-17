<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Feature;

use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\ComposioServiceProvider;
use BlockshiftNetwork\ComposioLaravel\Facades\Composio;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use BlockshiftNetwork\ComposioLaravel\Tools\ToolManager;
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

    public function test_binds_tool_manager(): void
    {
        $toolManager = $this->app->make(ToolManager::class);

        $this->assertInstanceOf(ToolManager::class, $toolManager);
        $this->assertNull($this->readToolManagerUserId($toolManager));
    }

    public function test_tool_manager_binding_ignores_legacy_default_user_id_config(): void
    {
        config(['services.composio.default_user_id' => 'legacy-global-user']);

        $toolManager = $this->app->make(ToolManager::class);

        $this->assertNull($this->readToolManagerUserId($toolManager));
    }

    public function test_config_is_merged(): void
    {
        $this->assertEquals('test-api-key', config('services.composio.api_key'));
        $this->assertEquals('https://test.composio.dev', config('services.composio.base_url'));
        $this->assertNull(config('services.composio.default_user_id'));
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

    public function test_tool_manager_inherits_custom_tools_registry(): void
    {
        $manager = $this->app->make(ComposioManager::class);
        $manager->customTools()->register('LOCAL_PING', 'ping', [], fn () => 'pong');

        $toolManager = $manager->tools();
        $registry = $toolManager->customTools();

        $this->assertNotNull($registry);
        $this->assertTrue($registry->has('LOCAL_PING'));
    }

    private function readToolManagerUserId(ToolManager $toolManager): ?string
    {
        $property = new \ReflectionProperty($toolManager, 'userId');

        return $property->getValue($toolManager);
    }
}
