<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Session;

use BlockshiftNetwork\Composio\Api\ToolRouterApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolRouterSessionBySessionIdLinkRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\SessionToolExecutor;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use Laravel\Ai\Contracts\Tool;
use Prism\Prism\Tool as PrismTool;

class ComposioSession
{
    /**
     * @param  array<string, mixed>  $mcp
     * @param  array<int, string>  $toolSlugs
     * @param  array<int, mixed>  $warnings
     */
    public function __construct(
        private readonly ToolRouterApi $toolRouterApi,
        private readonly ToolsApi $toolsApi,
        private readonly SessionToolExecutor $executor,
        private readonly ?PrismToolConverter $prismConverter,
        private readonly ?LaravelAiToolConverter $laravelAiConverter,
        public readonly string $sessionId,
        public readonly array $mcp = [],
        private readonly array $toolSlugs = [],
        public readonly mixed $preload = null,
        public readonly ?int $configVersion = null,
        public readonly array $warnings = [],
    ) {}

    /**
     * @return PrismTool[]
     */
    public function tools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
    ): array {
        $this->ensurePrismAvailable();

        return array_map(
            fn (ComposioToolModel $tool): PrismTool => $this->prismConverter->convert($tool),
            $this->fetchTools($toolkitSlug, $toolSlugs ?? $this->toolSlugs, $tags, $search),
        );
    }

    public function tool(string $toolSlug): PrismTool
    {
        $this->ensurePrismAvailable();

        $response = $this->toolsApi->getToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $this->prismConverter->convert($response);
    }

    /**
     * @return Tool[]
     */
    public function laravelAiTools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
    ): array {
        $this->ensureLaravelAiAvailable();

        return array_map(
            fn (ComposioToolModel $tool): mixed => $this->laravelAiConverter->convert($tool),
            $this->fetchTools($toolkitSlug, $toolSlugs ?? $this->toolSlugs, $tags, $search),
        );
    }

    public function laravelAiTool(string $toolSlug): mixed
    {
        $this->ensureLaravelAiAvailable();

        $response = $this->toolsApi->getToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $this->laravelAiConverter->convert($response);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        return $this->executor->execute($toolSlug, $arguments);
    }

    public function authorize(string $toolkit, ?string $callbackUrl = null): mixed
    {
        $request = new PostToolRouterSessionBySessionIdLinkRequest;
        $request->setToolkit($toolkit);

        if ($callbackUrl !== null) {
            $request->setCallbackUrl($callbackUrl);
        }

        $response = $this->toolRouterApi->postToolRouterSessionBySessionIdLink($this->sessionId, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to authorize toolkit '{$toolkit}' for session '{$this->sessionId}': ".$response->getError());
        }

        return $response;
    }

    public function toolkits(
        ?int $limit = null,
        ?string $cursor = null,
        array|string|null $toolkits = null,
        bool $isConnected = false,
    ): mixed {
        $response = $this->toolRouterApi->getToolRouterSessionBySessionIdToolkits(
            $this->sessionId,
            $limit,
            $cursor,
            is_array($toolkits) ? $toolkits : ($toolkits === null ? null : [$toolkits]),
            $isConnected,
        );

        if ($response instanceof Error) {
            throw new ComposioException("Failed to list toolkits for session '{$this->sessionId}': ".$response->getError());
        }

        return $response;
    }

    public function search(array $params = []): mixed
    {
        throw new ComposioException('Tool Router search is not exposed by composio-php v1 yet.');
    }

    public function update(array|SessionConfig $config): mixed
    {
        throw new ComposioException('Tool Router session update is not exposed by composio-php v1 yet.');
    }

    public function proxyExecute(array $params): mixed
    {
        throw new ComposioException('Tool Router proxy execution is not exposed by composio-php v1 yet.');
    }

    /**
     * @return array<int, ComposioToolModel>
     */
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
                tool_slugs: $this->csv($toolSlugs),
                tags: $tags,
                search: $search,
                cursor: $cursor,
            );

            if ($response instanceof Error) {
                throw new ComposioException('Failed to fetch session tools: '.$response->getError());
            }

            /** @var mixed $items */
            $items = $response->getItems();
            $allTools = array_merge($allTools, is_array($items) ? $items : []);
            $nextCursor = $response->getNextCursor();
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;
        } while ($cursor !== null);

        return $allTools;
    }

    private function csv(?array $value): ?string
    {
        return $value === null || $value === [] ? null : implode(',', $value);
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
