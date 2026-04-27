<?php

namespace BlockshiftNetwork\ComposioLaravel\Toolkits;

use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolkitsMultiRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class ToolkitManager
{
    public function __construct(
        private readonly ToolkitsApi $api,
    ) {}

    public function list(
        ?string $category = null,
        ?string $managedBy = null,
        ?bool $isLocal = null,
        ?string $sortBy = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getToolkits(
            category: $category,
            managed_by: $managedBy,
            is_local: $isLocal,
            sort_by: $sortBy,
            limit: $limit,
            cursor: $cursor,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list toolkits: '.$response->getError());
        }

        return $response;
    }

    public function get(string $slug, string $version = 'latest'): mixed
    {
        $response = $this->api->getToolkitsBySlug($slug, $version);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get toolkit '{$slug}': ".$response->getError());
        }

        return $response;
    }

    public function categories(?string $cache = null): mixed
    {
        $response = $this->api->getToolkitsCategories($cache);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list toolkit categories: '.$response->getError());
        }

        return $response;
    }

    public function changelog(): mixed
    {
        $response = $this->api->getToolkitsChangelog();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch toolkits changelog: '.$response->getError());
        }

        return $response;
    }

    public function fetchMultiple(PostToolkitsMultiRequest $request): mixed
    {
        $response = $this->api->postToolkitsMulti($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch multiple toolkits: '.$response->getError());
        }

        return $response;
    }
}
