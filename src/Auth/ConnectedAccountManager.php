<?php

namespace BlockshiftNetwork\ComposioLaravel\Auth;

use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostConnectedAccountsRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class ConnectedAccountManager
{
    public function __construct(
        private ConnectedAccountsApi $api,
    ) {}

    public function list(
        ?array $toolkitSlugs = null,
        ?array $statuses = null,
        ?array $userIds = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getConnectedAccounts(
            toolkit_slugs: $toolkitSlugs,
            statuses: $statuses,
            user_ids: $userIds,
            limit: $limit,
            cursor: $cursor,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list connected accounts: '.$response->getError());
        }

        return $response;
    }

    public function get(string $connectedAccountId): mixed
    {
        $response = $this->api->getConnectedAccountsByNanoid($connectedAccountId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }

    public function create(PostConnectedAccountsRequest $request): mixed
    {
        $response = $this->api->postConnectedAccounts($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create connected account: '.$response->getError());
        }

        return $response;
    }

    public function refresh(string $connectedAccountId, ?string $redirectUrl = null): mixed
    {
        $response = $this->api->postConnectedAccountsByNanoidRefresh(
            $connectedAccountId,
            $redirectUrl,
        );

        if ($response instanceof Error) {
            throw new ComposioException("Failed to refresh connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }

    public function delete(string $connectedAccountId): mixed
    {
        $response = $this->api->deleteConnectedAccountsByNanoid($connectedAccountId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }
}
