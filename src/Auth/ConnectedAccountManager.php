<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Auth;

use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchV31ConnectedAccountsByNanoIdStatusRequest;
use BlockshiftNetwork\Composio\Model\PostV31ConnectedAccountsLinkRequest;
use BlockshiftNetwork\Composio\Model\PostV31ConnectedAccountsRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class ConnectedAccountManager
{
    public function __construct(
        private readonly ConnectedAccountsApi $api,
    ) {}

    public function list(
        ?array $toolkitSlugs = null,
        ?array $statuses = null,
        ?array $userIds = null,
        ?array $authConfigIds = null,
        ?array $connectedAccountIds = null,
        ?string $orderBy = 'created_at',
        ?string $orderDirection = 'desc',
        ?string $accountType = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getV31ConnectedAccounts(
            toolkit_slugs: $toolkitSlugs,
            statuses: $statuses,
            cursor: $cursor,
            limit: $limit,
            user_ids: $userIds,
            auth_config_ids: $authConfigIds,
            connected_account_ids: $connectedAccountIds,
            order_by: $orderBy,
            order_direction: $orderDirection,
            account_type: $accountType,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list connected accounts: '.$response->getError());
        }

        return $response;
    }

    public function link(
        string $userId,
        string $authConfigId,
        ?string $callbackUrl = null,
        mixed $connectionData = null,
    ): mixed {
        $request = new PostV31ConnectedAccountsLinkRequest;
        $request->setUserId($userId);
        $request->setAuthConfigId($authConfigId);

        if ($callbackUrl !== null) {
            $request->setCallbackUrl($callbackUrl);
        }

        if ($connectionData !== null) {
            $request->setConnectionData($connectionData);
        }

        $response = $this->api->postV31ConnectedAccountsLink($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create connected account link: '.$response->getError());
        }

        return $response;
    }

    public function get(string $connectedAccountId): mixed
    {
        $response = $this->api->getV31ConnectedAccountsByNanoid($connectedAccountId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }

    public function create(PostV31ConnectedAccountsRequest $request): mixed
    {
        $response = $this->api->postV31ConnectedAccounts($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to create connected account: '.$response->getError());
        }

        return $response;
    }

    public function refresh(string $connectedAccountId, ?string $redirectUrl = null): mixed
    {
        $response = $this->api->postV31ConnectedAccountsByNanoidRefresh(
            $connectedAccountId,
            $redirectUrl,
        );

        if ($response instanceof Error) {
            throw new ComposioException("Failed to refresh connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }

    public function updateStatus(string $connectedAccountId, bool $enabled): mixed
    {
        $request = new PatchV31ConnectedAccountsByNanoIdStatusRequest;
        $request->setEnabled($enabled);

        $response = $this->api->patchV31ConnectedAccountsByNanoIdStatus($connectedAccountId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to update connected account '{$connectedAccountId}' status: ".$response->getError());
        }

        return $response;
    }

    public function enable(string $connectedAccountId): mixed
    {
        return $this->updateStatus($connectedAccountId, true);
    }

    public function disable(string $connectedAccountId): mixed
    {
        return $this->updateStatus($connectedAccountId, false);
    }

    public function waitForConnection(
        string $connectedAccountId,
        int $timeoutSeconds = 120,
        int $intervalSeconds = 2,
    ): mixed {
        $deadline = time() + $timeoutSeconds;

        do {
            $account = $this->get($connectedAccountId);
            $status = $this->readStatus($account);

            if ($status === 'ACTIVE') {
                return $account;
            }

            if (in_array($status, ['FAILED', 'EXPIRED', 'INACTIVE'], true)) {
                throw new ComposioException("Connected account '{$connectedAccountId}' reached terminal status '{$status}'.");
            }

            if (time() >= $deadline) {
                break;
            }

            sleep(max(1, $intervalSeconds));
        } while (true);

        throw new ComposioException("Timed out waiting for connected account '{$connectedAccountId}' to become active.");
    }

    public function delete(string $connectedAccountId): mixed
    {
        $response = $this->api->deleteV31ConnectedAccountsByNanoid($connectedAccountId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete connected account '{$connectedAccountId}': ".$response->getError());
        }

        return $response;
    }

    private function readStatus(mixed $account): ?string
    {
        if (is_object($account) && method_exists($account, 'getStatus')) {
            $status = $account->getStatus();

            return is_string($status) ? strtoupper($status) : null;
        }

        if (is_array($account) && isset($account['status']) && is_string($account['status'])) {
            return strtoupper($account['status']);
        }

        if ($account instanceof \ArrayAccess && $account->offsetExists('status')) {
            $status = $account->offsetGet('status');

            return is_string($status) ? strtoupper($status) : null;
        }

        return null;
    }
}
