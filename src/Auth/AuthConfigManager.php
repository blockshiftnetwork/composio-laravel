<?php

namespace BlockshiftNetwork\ComposioLaravel\Auth;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostV31AuthConfigsRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class AuthConfigManager
{
    public function __construct(
        private readonly AuthConfigsApi $api,
    ) {}

    public function list(
        ?string $toolkitSlug = null,
        ?string $search = null,
    ): mixed {
        $response = $this->api->getV31AuthConfigs(
            toolkit_slug: $toolkitSlug,
            search: $search,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list auth configs: '.$response->getError());
        }

        return $response;
    }

    public function get(string $authConfigId): mixed
    {
        $response = $this->api->getV31AuthConfigsByNanoid($authConfigId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }

    public function create(PostV31AuthConfigsRequest $request): mixed
    {
        $response = $this->api->postV31AuthConfigs($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create auth config: '.$response->getError());
        }

        return $response;
    }

    public function update(string $authConfigId, mixed $request): mixed
    {
        $response = $this->api->patchV31AuthConfigsByNanoid($authConfigId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to update auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }

    public function updateStatus(string $authConfigId, string $status): mixed
    {
        $response = $this->api->patchV31AuthConfigsByNanoidByStatus($authConfigId, $status);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to update auth config '{$authConfigId}' status: ".$response->getError());
        }

        return $response;
    }

    public function enable(string $authConfigId): mixed
    {
        return $this->updateStatus($authConfigId, 'ENABLED');
    }

    public function disable(string $authConfigId): mixed
    {
        return $this->updateStatus($authConfigId, 'DISABLED');
    }

    public function delete(string $authConfigId): mixed
    {
        $response = $this->api->deleteV31AuthConfigsByNanoid($authConfigId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }
}
