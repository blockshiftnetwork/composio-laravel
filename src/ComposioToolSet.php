<?php

namespace BlockshiftNetwork\ComposioLaravel;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlugInputRequest;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteProxyRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\LaravelAi\CustomLaravelAiTool;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomTool;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use Prism\Prism\Tool;

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
        private ?CustomToolRegistry $customTools = null,
        private ?SchemaMapper $schemaMapper = null,
        private ?LaravelAiSchemaMapper $laravelAiSchemaMapper = null,
    ) {}

    // --- PrismPHP methods ---

    /** @return Tool[] */
    public function getTools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
    ): array {
        $this->ensurePrismAvailable();
        $composioTools = $this->fetchTools($toolkitSlug, $toolSlugs, $tags, $search);

        $remote = array_map(
            fn (ComposioTool $tool): Tool => $this->prismConverter->convert(
                $tool, $this->userId, $this->entityId, $this->connectedAccountId,
            ),
            $composioTools,
        );

        return array_merge($remote, $this->customPrismTools());
    }

    public function getTool(string $toolSlug): mixed
    {
        $this->ensurePrismAvailable();

        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToPrism($this->customTools->get($toolSlug));
        }

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

        $remote = array_map(
            fn (ComposioTool $tool): LaravelAi\ComposioTool => $this->laravelAiConverter->convert(
                $tool, $this->userId, $this->entityId, $this->connectedAccountId,
            ),
            $composioTools,
        );

        return array_merge($remote, $this->customLaravelAiTools());
    }

    public function getLaravelAiTool(string $toolSlug): mixed
    {
        $this->ensureLaravelAiAvailable();

        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToLaravelAi($this->customTools->get($toolSlug));
        }

        $composioTool = $this->fetchTool($toolSlug);

        return $this->laravelAiConverter->convert(
            $composioTool, $this->userId, $this->entityId, $this->connectedAccountId,
        );
    }

    // --- Shared methods ---

    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->executeCustomTool($this->customTools->get($toolSlug), $arguments);
        }

        return $this->executor->execute(
            $toolSlug, $arguments, $this->userId, $this->entityId, $this->connectedAccountId,
        );
    }

    public function enums(): mixed
    {
        $response = $this->toolsApi->getToolsEnum();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch tool enums: '.$response->getError());
        }

        return $response;
    }

    public function generateInputs(string $toolSlug, string $text): mixed
    {
        $request = new PostToolsExecuteByToolSlugInputRequest;
        $request->setText($text);

        $response = $this->toolsApi->postToolsExecuteByToolSlugInput($toolSlug, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to generate inputs for '{$toolSlug}': ".$response->getError());
        }

        return $response;
    }

    public function proxyExecute(PostToolsExecuteProxyRequest $request): mixed
    {
        $response = $this->toolsApi->postToolsExecuteProxy($request);

        if ($response instanceof Error) {
            throw new ComposioException('Failed to execute proxy request: '.$response->getError());
        }

        return $response;
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

    public function customTools(): ?CustomToolRegistry
    {
        return $this->customTools;
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

    /** @return Tool[] */
    private function customPrismTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map($this->customToPrism(...), $this->customTools->all());
    }

    /** @return \Laravel\Ai\Contracts\Tool[] */
    private function customLaravelAiTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map(
            $this->customToLaravelAi(...),
            $this->customTools->all(),
        );
    }

    private function customToPrism(CustomTool $custom): Tool
    {
        if ($this->schemaMapper === null) {
            throw new ComposioException('SchemaMapper is required to expose custom tools to PrismPHP.');
        }

        $tool = (new Tool)->as($custom->slug)->for($custom->description);

        if ($custom->inputSchema !== []) {
            $tool = $this->schemaMapper->applySchema($tool, $custom->inputSchema);
        }

        return $tool->using(function () use ($custom): string {
            $arguments = func_get_args();
            $namedArgs = count($arguments) === 1 && is_array($arguments[0]) ? $arguments[0] : $arguments;

            return $custom->execute($namedArgs);
        });
    }

    private function customToLaravelAi(CustomTool $custom): CustomLaravelAiTool
    {
        if ($this->laravelAiSchemaMapper === null) {
            throw new ComposioException('LaravelAiSchemaMapper is required to expose custom tools to Laravel AI.');
        }

        return new CustomLaravelAiTool($custom, $this->laravelAiSchemaMapper);
    }

    private function executeCustomTool(CustomTool $custom, array $arguments): ExecutionResult
    {
        $arguments = $this->hooks->runBefore($custom->slug, $arguments);

        try {
            $output = $custom->execute($arguments);
            $data = $this->decodeOutput($output);
            $result = ExecutionResult::synthetic(successful: true, data: $data);
        } catch (\Throwable $e) {
            $result = ExecutionResult::synthetic(successful: false, error: $e->getMessage());
        }

        return $this->hooks->runAfter($custom->slug, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOutput(string $output): array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) ? $decoded : ['output' => $output];
    }
}
