<?php

namespace BlockshiftNetwork\ComposioLaravel\Triggers;

use BlockshiftNetwork\Composio\Api\TriggersApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchTriggerInstancesManageByTriggerIdRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class TriggerManager
{
    public function __construct(
        private readonly TriggersApi $api,
    ) {}

    public function listTypes(
        ?array $toolkitSlugs = null,
        mixed $toolkitVersions = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getTriggersTypes(
            toolkit_slugs: $toolkitSlugs,
            toolkit_versions: $toolkitVersions,
            limit: $limit,
            cursor: $cursor,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list trigger types: '.$response->getError());
        }

        return $response;
    }

    public function getType(string $slug, mixed $toolkitVersions = null): mixed
    {
        $response = $this->api->getTriggersTypesBySlug($slug, $toolkitVersions);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get trigger type '{$slug}': ".$response->getError());
        }

        return $response;
    }

    public function listTypesEnum(): mixed
    {
        $response = $this->api->getTriggersTypesListEnum();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch trigger types enum: '.$response->getError());
        }

        return $response;
    }

    public function listInstances(
        ?array $connectedAccountIds = null,
        ?array $authConfigIds = null,
        ?array $triggerIds = null,
        ?array $triggerNames = null,
        bool $showDisabled = false,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getTriggerInstancesActive(
            connected_account_ids: $connectedAccountIds,
            auth_config_ids: $authConfigIds,
            trigger_ids: $triggerIds,
            trigger_names: $triggerNames,
            connected_account_ids2: null,
            auth_config_ids2: null,
            trigger_ids2: null,
            show_disabled: $showDisabled,
            trigger_names2: null,
            page: 1,
            show_disabled2: $showDisabled,
            deprecated_connected_account_uuids: null,
            deprecated_auth_config_uuids: null,
            limit: $limit,
            cursor: $cursor,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list trigger instances: '.$response->getError());
        }

        return $response;
    }

    public function upsert(string $triggerSlug, mixed $request): mixed
    {
        $response = $this->api->postTriggerInstancesBySlugUpsert($triggerSlug, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to upsert trigger instance '{$triggerSlug}': ".$response->getError());
        }

        return $response;
    }

    public function enable(string $triggerId): mixed
    {
        return $this->setStatus($triggerId, 'enable');
    }

    public function disable(string $triggerId): mixed
    {
        return $this->setStatus($triggerId, 'disable');
    }

    public function setStatus(string $triggerId, string $status): mixed
    {
        $request = new PatchTriggerInstancesManageByTriggerIdRequest;
        $request->setStatus($status);

        $response = $this->api->patchTriggerInstancesManageByTriggerId($triggerId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to {$status} trigger instance '{$triggerId}': ".$response->getError());
        }

        return $response;
    }

    public function delete(string $triggerId): mixed
    {
        $response = $this->api->deleteTriggerInstancesManageByTriggerId($triggerId);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to delete trigger instance '{$triggerId}': ".$response->getError());
        }

        return $response;
    }
}
