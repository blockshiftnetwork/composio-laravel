<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Facades;

use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BlockshiftNetwork\ComposioLaravel\ComposioToolSet toolSet(?string $userId = null, ?string $entityId = null)
 * @method static \BlockshiftNetwork\ComposioLaravel\Entities\Entity entity(string $userId)
 * @method static \BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager connectedAccounts()
 * @method static \BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager authConfigs()
 * @method static \BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager toolkits()
 * @method static \BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager triggers()
 * @method static \BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager mcp()
 * @method static \BlockshiftNetwork\ComposioLaravel\Files\FileManager files()
 * @method static \BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry customTools()
 * @method static object api(string $apiClass)
 *
 * @see ComposioManager
 */
class Composio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ComposioManager::class;
    }
}
