<?php

declare(strict_types=1);

use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use BlockshiftNetwork\ComposioLaravel\Tools\ToolManager;
use BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager;
use GuzzleHttp\Client;

require dirname(__DIR__, 2).'/vendor/autoload.php';

spl_autoload_register(
    function (string $class): void {
        if (
            str_starts_with($class, 'Prism\\')
            || str_starts_with($class, 'Laravel\\Ai\\')
            || str_starts_with($class, 'Illuminate\\JsonSchema\\')
        ) {
            throw new RuntimeException("Optional adapter class was autoloaded: {$class}");
        }
    },
    throw: true,
    prepend: true,
);

$manager = new ComposioManager(
    Configuration::getDefaultConfiguration()
        ->setApiKey('x-api-key', 'test-api-key')
        ->setHost('https://test.composio.dev'),
    new Client,
);

assertInstanceOf(ConnectedAccountManager::class, $manager->connectedAccounts());
assertInstanceOf(AuthConfigManager::class, $manager->authConfigs());
assertInstanceOf(ToolkitManager::class, $manager->toolkits());
assertInstanceOf(TriggerManager::class, $manager->triggers());
assertInstanceOf(McpServerManager::class, $manager->mcp());
assertInstanceOf(FileManager::class, $manager->files());

$manager->customTools()->register('LOCAL_PING', 'Respond with pong.', [], fn (): array => ['pong' => true]);

$tools = $manager->tools();
assertInstanceOf(ToolManager::class, $tools);

$result = $tools->execute('LOCAL_PING');

if (! $result->isSuccessful() || $result->data() !== ['pong' => true]) {
    throw new RuntimeException('Core custom tool execution failed without optional adapters.');
}

function assertInstanceOf(string $expected, object $actual): void
{
    if (! $actual instanceof $expected) {
        throw new RuntimeException(sprintf(
            'Expected instance of %s, got %s.',
            $expected,
            $actual::class,
        ));
    }
}
