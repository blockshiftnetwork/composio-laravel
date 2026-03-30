<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ToolExecutionException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\ToolConverterInterface;

class ComposioToolSet
{
    public function __construct(
        private ToolsApi $toolsApi,
        private ?PrismToolConverter $prismConverter,
        private ?LaravelAiToolConverter $laravelAiConverter,
        private ToolExecutor $executor,
        private HookManager $hooks,
        private ?string $userId = null,
        private ?string $entityId = null,
        private ?string $connectedAccountId = null,
    ) {}

    // --- PrismPHP methods ---

    /** @return \Prism\Prism\Tool[] */
    public function getTools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
    ): array {
        $this->ensurePrismAvailable();
        $composioTools = $this->fetchTools($toolkitSlug, $toolSlugs, $tags, $search);

        return array_map(
            fn (ComposioTool $tool): \Prism\Prism\Tool => $this->prismConverter->convert(
                $tool, $this->userId, $this->entityId, $this->connectedAccountId,
            ),
            $composioTools,
        );
    }

    public function getTool(string $toolSlug): mixed
    {
        $this->ensurePrismAvailable();
        $composioTool = $this->fetchTool($toolSlug);

        return $this->prismConverter->convert(
            $composioTool, $this->userId, $this->entityId, $this->connectedAccountId,
        );
    }

    // --- Laravel AI methods ---

    /** @return \Laravel\Ai\Contracts\Tool[] */
    public function getLaravelAiTools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
    ): array {
        $this->ensureLaravelAiAvailable();
        $composioTools = $this->fetchTools($toolkitSlug, $toolSlugs, $tags, $search);

        return array_map(
            fn (ComposioTool $tool): \BlockshiftNetwork\ComposioLaravel\LaravelAi\ComposioTool => $this->laravelAiConverter->convert(
                $tool, $this->userId, $this->entityId, $this->connectedAccountId,
            ),
            $composioTools,
        );
    }

    public function getLaravelAiTool(string $toolSlug): mixed
    {
        $this->ensureLaravelAiAvailable();
        $composioTool = $this->fetchTool($toolSlug);

        return $this->laravelAiConverter->convert(
            $composioTool, $this->userId, $this->entityId, $this->connectedAccountId,
        );
    }

    // --- Shared methods ---

    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        return $this->executor->execute(
            $toolSlug, $arguments, $this->userId, $this->entityId, $this->connectedAccountId,
        );
    }

    public function forUser(string $userId): self
    {
        $clone = clone $this;
        $clone->userId = $userId;

        return $clone;
    }

    public function forEntity(string $entityId): self
    {
        $clone = clone $this;
        $clone->entityId = $entityId;

        return $clone;
    }

    public function withConnectedAccount(string $connectedAccountId): self
    {
        $clone = clone $this;
        $clone->connectedAccountId = $connectedAccountId;

        return $clone;
    }

    public function hooks(): HookManager
    {
        return $this->hooks;
    }

    // --- Internal ---

    /** @return ComposioTool[] */
    private function fetchTools(
        ?string $toolkitSlug,
        ?array $toolSlugs,
        ?array $tags,
        ?string $search,
    ): array {
        $allTools = [];
        $cursor = null;

        do {
            $response = $this->toolsApi->getTools(
                toolkit_slug: $toolkitSlug,
                tool_slugs: $toolSlugs,
                tags: $tags,
                search: $search,
                cursor: $cursor,
            );

            if ($response instanceof Error) {
                throw new ComposioException('Failed to fetch tools: '.$response->getError());
            }

            $items = $response->getItems() ?? [];
            $allTools = array_merge($allTools, $items);
            $cursor = $response->getNextCursor();
        } while ($cursor !== null);

        return $allTools;
    }

    private function fetchTool(string $toolSlug): ComposioTool
    {
        $response = $this->toolsApi->getToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $response;
    }

    private function ensurePrismAvailable(): void
    {
        if ($this->prismConverter === null) {
            throw new ComposioException(
                'PrismPHP is not available. Install it with: composer require prism-php/prism'
            );
        }
    }

    private function ensureLaravelAiAvailable(): void
    {
        if ($this->laravelAiConverter === null) {
            throw new ComposioException(
                'Laravel AI is not available. Install it with: composer require laravel/ai'
            );
        }
    }
}
