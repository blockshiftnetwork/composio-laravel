<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Api\FilesApi;
use BlockshiftNetwork\Composio\Api\MCPApi;
use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Api\TriggersApi;
use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager;
use GuzzleHttp\ClientInterface;
use Prism\Prism\Tool;

class ComposioManager
{
    private ?ConnectedAccountManager $connectedAccountManager = null;

    private ?AuthConfigManager $authConfigManager = null;

    private ?ToolkitManager $toolkitManager = null;

    private ?TriggerManager $triggerManager = null;

    private ?McpServerManager $mcpServerManager = null;

    private ?FileManager $fileManager = null;

    private ?CustomToolRegistry $customToolRegistry = null;

    public function __construct(
        private readonly Configuration $config,
        private readonly ClientInterface $httpClient,
    ) {}

    public function toolSet(?string $userId = null, ?string $entityId = null): ComposioToolSet
    {
        $toolsApi = new ToolsApi($this->httpClient, $this->config);
        $hookManager = new HookManager;
        $executor = new ToolExecutor($toolsApi, $hookManager);

        $prismConverter = null;
        $schemaMapper = null;
        if (class_exists(Tool::class)) {
            $schemaMapper = new SchemaMapper;
            $prismConverter = new PrismToolConverter($schemaMapper, $executor);
        }

        $laravelAiConverter = null;
        $laravelAiSchemaMapper = null;
        if (class_exists(\Laravel\Ai\Contracts\Tool::class)) {
            $laravelAiSchemaMapper = new LaravelAiSchemaMapper;
            $laravelAiConverter = new LaravelAiToolConverter($laravelAiSchemaMapper, $executor);
        }

        return new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: $prismConverter,
            laravelAiConverter: $laravelAiConverter,
            executor: $executor,
            hooks: $hookManager,
            userId: $userId,
            entityId: $entityId,
            customTools: $this->customTools(),
            schemaMapper: $schemaMapper,
            laravelAiSchemaMapper: $laravelAiSchemaMapper,
        );
    }

    public function connectedAccounts(): ConnectedAccountManager
    {
        return $this->connectedAccountManager ??= new ConnectedAccountManager(
            new ConnectedAccountsApi($this->httpClient, $this->config)
        );
    }

    public function authConfigs(): AuthConfigManager
    {
        return $this->authConfigManager ??= new AuthConfigManager(
            new AuthConfigsApi($this->httpClient, $this->config)
        );
    }

    public function toolkits(): ToolkitManager
    {
        return $this->toolkitManager ??= new ToolkitManager(
            new ToolkitsApi($this->httpClient, $this->config)
        );
    }

    public function triggers(): TriggerManager
    {
        return $this->triggerManager ??= new TriggerManager(
            new TriggersApi($this->httpClient, $this->config)
        );
    }

    public function mcp(): McpServerManager
    {
        return $this->mcpServerManager ??= new McpServerManager(
            new MCPApi($this->httpClient, $this->config)
        );
    }

    public function files(): FileManager
    {
        return $this->fileManager ??= new FileManager(
            new FilesApi($this->httpClient, $this->config)
        );
    }

    public function customTools(): CustomToolRegistry
    {
        return $this->customToolRegistry ??= new CustomToolRegistry;
    }

    public function api(string $apiClass): object
    {
        return new $apiClass($this->httpClient, $this->config);
    }
}
