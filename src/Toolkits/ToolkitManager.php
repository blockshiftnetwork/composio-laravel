<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Toolkits;

use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostV31ToolkitsMultiRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class ToolkitManager
{
    public function __construct(
        private readonly ToolkitsApi $api,
    ) {}

    public function list(
        ?string $category = null,
        ?string $managedBy = null,
        ?bool $includeDeprecated = null,
        ?string $sortBy = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getV31Toolkits(
            category: $category,
            managed_by: $managedBy,
            sort_by: $sortBy,
            include_deprecated: $includeDeprecated ?? false,
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
        $response = $this->api->getV31ToolkitsBySlug($slug, $version);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to get toolkit '{$slug}': ".$response->getError());
        }

        return $response;
    }

    public function categories(): mixed
    {
        $response = $this->api->getV31ToolkitsCategories();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list toolkit categories: '.$response->getError());
        }

        return $response;
    }

    public function changelog(): mixed
    {
        $response = $this->api->getV31ToolkitsChangelog();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch toolkits changelog: '.$response->getError());
        }

        return $response;
    }

    public function fetchMultiple(PostV31ToolkitsMultiRequest $request): mixed
    {
        $response = $this->api->postV31ToolkitsMulti($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch multiple toolkits: '.$response->getError());
        }

        return $response;
    }
}
