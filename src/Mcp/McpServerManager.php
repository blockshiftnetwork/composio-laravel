<?php

namespace BlockshiftNetwork\ComposioLaravel\Mcp;

use BlockshiftNetwork\Composio\Api\MCPApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchMcpByIdRequest;
use BlockshiftNetwork\Composio\Model\PostMcpServersCustomRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class McpServerManager
{
    public function __construct(
        private readonly MCPApi $api,
    ) {}

    /**
     * @param  array<string>|string|null  $toolkits  Toolkit slugs (array or comma-separated string)
     * @param  array<string>|string|null  $authConfigIds  Auth config IDs (array or comma-separated string)
     */
    public function list(
        ?string $name = null,
        array|string|null $toolkits = null,
        array|string|null $authConfigIds = null,
        string $orderBy = 'updated_at',
        string $orderDirection = 'desc',
        int $page = 1,
        int $limit = 10,
    ): mixed {
        $response = $this->api->getMcpServers(
            name: $name,
            toolkits: is_array($toolkits) ? implode(',', $toolkits) : $toolkits,
            auth_config_ids: is_array($authConfigIds) ? implode(',', $authConfigIds) : $authConfigIds,
            order_by: $orderBy,
            order_direction: $orderDirection,
            page_no: $page,
            limit: $limit,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list MCP servers: '.$response->getError());
        }

        return $response;
    }

    public function get(string $serverId): mixed
    {
        $response = $this->api->getMcpById($serverId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }

    public function createCustomServer(PostMcpServersCustomRequest $request): mixed
    {
        $response = $this->api->postMcpServersCustom($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create custom MCP server: '.$response->getError());
        }

        return $response;
    }

    public function update(string $serverId, PatchMcpByIdRequest $request): mixed
    {
        $response = $this->api->patchMcpById($serverId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to update MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }

    public function delete(string $serverId): mixed
    {
        $response = $this->api->deleteMcpById($serverId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }

    public function listInstances(
        string $serverId,
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        string $orderBy = 'updated_at',
        string $orderDirection = 'desc',
    ): mixed {
        $response = $this->api->getMcpServersByServerIdInstances(
            server_id: $serverId,
            page_no: $page,
            limit: $limit,
            search: $search,
            order_by: $orderBy,
            order_direction: $orderDirection,
        );

        if ($response instanceof Error) {
            throw new ComposioException("Failed to list instances for MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }

    public function createInstance(string $serverId, mixed $request): mixed
    {
        $response = $this->api->postMcpServersByServerIdInstances($serverId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to create instance for MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }

    public function deleteInstance(string $serverId, string $instanceId): mixed
    {
        $response = $this->api->deleteMcpServersByServerIdInstancesByInstanceId($serverId, $instanceId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete instance '{$instanceId}' of MCP server '{$serverId}': ".$response->getError());
        }

        return $response;
    }
}
