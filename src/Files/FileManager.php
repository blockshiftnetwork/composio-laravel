<?php

namespace BlockshiftNetwork\ComposioLaravel\Files;

use BlockshiftNetwork\Composio\Api\FilesApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;

class FileManager
{
    public function __construct(
        private readonly FilesApi $api,
    ) {}

    public function list(
        ?string $toolkitSlug = null,
        ?string $toolSlug = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): mixed {
        $response = $this->api->getFilesList(
            toolkit_slug: $toolkitSlug,
            tool_slug: $toolSlug,
            limit: $limit,
            cursor: $cursor,
        );

        if ($response instanceof Error) {
            throw new ComposioException('Failed to list files: '.$response->getError());
        }

        return $response;
    }
}
