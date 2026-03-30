<?php

namespace BlockshiftNetwork\ComposioLaravel\Auth;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostAuthConfigsRequest;
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
        $response = $this->api->getAuthConfigs(
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
        $response = $this->api->getAuthConfigsByNanoid($authConfigId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }

    public function create(PostAuthConfigsRequest $request): mixed
    {
        $response = $this->api->postAuthConfigs($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create auth config: '.$response->getError());
        }

        return $response;
    }

    public function update(string $authConfigId, mixed $request): mixed
    {
        $response = $this->api->patchAuthConfigsByNanoid($authConfigId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to update auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }

    public function delete(string $authConfigId): mixed
    {
        $response = $this->api->deleteAuthConfigsByNanoid($authConfigId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete auth config '{$authConfigId}': ".$response->getError());
        }

        return $response;
    }
}
