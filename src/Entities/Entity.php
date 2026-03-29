<?php

namespace BlockshiftNetwork\ComposioLaravel\Entities;

use BlockshiftNetwork\Composio\Model\PostConnectedAccountsRequest;
use BlockshiftNetwork\ComposioLaravel\ComposioManager;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;

class Entity
{
    public function __construct(
        private ComposioManager $manager,
        private string $userId,
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
