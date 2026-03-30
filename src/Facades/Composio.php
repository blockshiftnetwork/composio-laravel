<?php

namespace BlockshiftNetwork\ComposioLaravel\Facades;

use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \BlockshiftNetwork\ComposioLaravel\ComposioToolSet toolSet(?string $userId = null, ?string $entityId = null)
 * @method static \BlockshiftNetwork\ComposioLaravel\Entities\Entity entity(string $userId)
 * @method static \BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager connectedAccounts()
 * @method static \BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager authConfigs()
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
