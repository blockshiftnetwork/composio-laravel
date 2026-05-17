<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Api\FilesApi;
use BlockshiftNetwork\Composio\Api\MCPApi;
use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Api\ToolRouterApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Api\TriggersApi;
use BlockshiftNetwork\Composio\Configuration;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\SessionToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use BlockshiftNetwork\ComposioLaravel\Session\ComposioSession;
use BlockshiftNetwork\ComposioLaravel\Session\SessionConfig;
use BlockshiftNetwork\ComposioLaravel\Support\OptionalDependencyChecker;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use BlockshiftNetwork\ComposioLaravel\Tools\ToolManager;
use BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager;
use GuzzleHttp\ClientInterface;

class ComposioManager
{
    private ?ConnectedAccountManager $connectedAccountManager = null;

    private ?AuthConfigManager $authConfigManager = null;

    private ?ToolkitManager $toolkitManager = null;

    private ?TriggerManager $triggerManager = null;

    private ?McpServerManager $mcpServerManager = null;

    private ?FileManager $fileManager = null;

    private ?CustomToolRegistry $customToolRegistry = null;

    private ?ToolManager $toolManager = null;

    private readonly OptionalDependencyChecker $optionalDependencies;

    public function __construct(
        private readonly Configuration $config,
        private readonly ClientInterface $httpClient,
        ?OptionalDependencyChecker $optionalDependencies = null,
    ) {
        $this->optionalDependencies = $optionalDependencies ?? new OptionalDependencyChecker;
    }

    public function create(string $userId, array|SessionConfig $config = []): ComposioSession
    {
        $sessionConfig = $config instanceof SessionConfig ? $config : SessionConfig::fromArray($config);
        $response = $this->toolRouterApi()->postV31ToolRouterSession(
            $this->rawRequest($sessionConfig->toPayload($userId)),
        );

        return $this->sessionFromResponse($response);
    }

    public function use(string $sessionId): ComposioSession
    {
        $response = $this->toolRouterApi()->getV31ToolRouterSessionBySessionId($sessionId);

        return $this->sessionFromResponse($response);
    }

    public function tools(?string $userId = null): ToolManager
    {
        $manager = $this->toolManager ??= $this->makeToolManager();

        return $userId === null ? $manager : $manager->forUser($userId);
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

    private function makeToolManager(): ToolManager
    {
        $toolsApi = new ToolsApi($this->httpClient, $this->config);
        $hooks = new HookManager;
        $executor = new ToolExecutor($toolsApi, $hooks);

        return new ToolManager(
            toolsApi: $toolsApi,
            executor: $executor,
            hooks: $hooks,
            customTools: $this->customTools(),
            optionalDependencies: $this->optionalDependencies,
        );
    }

    private function makeSession(
        string $sessionId,
        array $mcp = [],
        array $toolSlugs = [],
        mixed $preload = null,
        ?int $configVersion = null,
        array $warnings = [],
    ): ComposioSession {
        $toolRouterApi = $this->toolRouterApi();
        $toolsApi = new ToolsApi($this->httpClient, $this->config);
        $hooks = new HookManager;
        $executor = new SessionToolExecutor($toolRouterApi, $hooks, $sessionId);

        return new ComposioSession(
            toolRouterApi: $toolRouterApi,
            toolsApi: $toolsApi,
            executor: $executor,
            sessionId: $sessionId,
            mcp: $mcp,
            toolSlugs: $toolSlugs,
            preload: $preload,
            configVersion: $configVersion,
            warnings: $warnings,
            optionalDependencies: $this->optionalDependencies,
        );
    }

    private function sessionFromResponse(mixed $response): ComposioSession
    {
        if ($response instanceof Error) {
            throw new ComposioException('Failed to create or fetch Tool Router session: '.$response->getError());
        }

        $sessionId = $this->readResponseValue($response, 'session_id', 'getSessionId');

        if (! is_string($sessionId) || $sessionId === '') {
            throw new ComposioException('Composio returned a Tool Router session without a session_id.');
        }

        return $this->makeSession(
            sessionId: $sessionId,
            mcp: $this->normalizeMcp($this->readResponseValue($response, 'mcp', 'getMcp')),
            toolSlugs: $this->normalizeStringList($this->readResponseValue($response, 'tool_router_tools', 'getToolRouterTools')),
            preload: $this->readResponseValue($response, 'preload'),
            configVersion: $this->normalizeInt($this->readResponseValue($response, 'config_version')),
            warnings: $this->normalizeList($this->readResponseValue($response, 'warnings')),
        );
    }

    private function toolRouterApi(): ToolRouterApi
    {
        return new ToolRouterApi($this->httpClient, $this->config);
    }

    private function rawRequest(mixed $request): mixed
    {
        return $request;
    }

    private function readResponseValue(mixed $source, string $key, ?string $getter = null): mixed
    {
        if ($getter !== null && is_object($source) && method_exists($source, $getter)) {
            return $source->{$getter}();
        }

        if (is_array($source) && array_key_exists($key, $source)) {
            return $source[$key];
        }

        if ($source instanceof \ArrayAccess && $source->offsetExists($key)) {
            return $source->offsetGet($key);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMcp(mixed $mcp): array
    {
        if (is_array($mcp)) {
            return $mcp;
        }

        if (is_object($mcp) && method_exists($mcp, 'getType') && method_exists($mcp, 'getUrl')) {
            return [
                'type' => $mcp->getType(),
                'url' => $mcp->getUrl(),
            ];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function normalizeInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
