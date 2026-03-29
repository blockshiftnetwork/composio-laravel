<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use GuzzleHttp\ClientInterface;

class ComposioManager
{
    private ?ConnectedAccountManager $connectedAccountManager = null;

    private ?AuthConfigManager $authConfigManager = null;

    public function __construct(
        private Configuration $config,
        private ClientInterface $httpClient,
    ) {}

    public function toolSet(?string $userId = null, ?string $entityId = null): ComposioToolSet
    {
        $toolsApi = new ToolsApi($this->httpClient, $this->config);
        $hookManager = new HookManager;
        $executor = new ToolExecutor($toolsApi, $hookManager);

        $prismConverter = null;
        if (class_exists(\Prism\Prism\Tool::class)) {
            $prismConverter = new PrismToolConverter(new SchemaMapper, $executor);
        }

        $laravelAiConverter = null;
        if (class_exists(\Laravel\Ai\Contracts\Tool::class)) {
            $laravelAiConverter = new LaravelAiToolConverter(new LaravelAiSchemaMapper, $executor);
        }

        return new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: $prismConverter,
            laravelAiConverter: $laravelAiConverter,
            executor: $executor,
            hooks: $hookManager,
            userId: $userId,
            entityId: $entityId,
        );
    }

    public function connectedAccounts(): ConnectedAccountManager
    {
        if ($this->connectedAccountManager === null) {
            $this->connectedAccountManager = new ConnectedAccountManager(
                new ConnectedAccountsApi($this->httpClient, $this->config)
            );
        }

        return $this->connectedAccountManager;
    }

    public function authConfigs(): AuthConfigManager
    {
        if ($this->authConfigManager === null) {
            $this->authConfigManager = new AuthConfigManager(
                new AuthConfigsApi($this->httpClient, $this->config)
            );
        }

        return $this->authConfigManager;
    }

    public function api(string $apiClass): object
    {
        return new $apiClass($this->httpClient, $this->config);
    }
}
