<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Entities;

use BlockshiftNetwork\Composio\Model\PostConnectedAccountsRequest;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;

class Entity
{
    public function __construct(
        private readonly ComposioManager $manager,
        private readonly string $userId,
    ) {}

    public function toolSet(): ComposioToolSet
    {
        return $this->manager->toolSet(userId: $this->userId);
    }

    public function connectedAccounts(): mixed
    {
        return $this->manager->connectedAccounts()->list(userIds: [$this->userId]);
    }

    public function initiateConnection(PostConnectedAccountsRequest $request): mixed
    {
        return $this->manager->connectedAccounts()->create($request);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
