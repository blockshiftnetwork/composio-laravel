<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tools;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlugInputRequest;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteProxyRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\ScopedToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutorInterface;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\LaravelAi\CustomLaravelAiTool;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use Laravel\Ai\Contracts\Tool;
use Prism\Prism\Tool as PrismTool;

class ToolManager
{
    /**
     * @param  callable(ToolExecutorInterface): PrismToolConverter|null  $prismConverterFactory
     * @param  callable(ToolExecutorInterface): LaravelAiToolConverter|null  $laravelAiConverterFactory
     */
    public function __construct(
        private readonly ToolsApi $toolsApi,
        private readonly mixed $prismConverterFactory,
        private readonly mixed $laravelAiConverterFactory,
        private readonly ToolExecutor $executor,
        private readonly HookManager $hooks,
        private readonly ?CustomToolRegistry $customTools = null,
        private readonly ?SchemaMapper $schemaMapper = null,
        private readonly ?LaravelAiSchemaMapper $laravelAiSchemaMapper = null,
        private readonly ?string $userId = null,
        private readonly ?string $connectedAccountId = null,
        private readonly ?string $version = null,
    ) {}

    /**
     * @return PrismTool[]
     */
    public function get(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
        ?array $authConfigIds = null,
        ?bool $important = null,
        ?array $scopes = null,
        bool $includeDeprecated = true,
        mixed $toolkitVersions = null,
        ?int $limit = null,
    ): array {
        $converter = $this->prismConverter();

        $remote = array_map(
            $converter->convert(...),
            $this->fetchTools(
                toolkitSlug: $toolkitSlug,
                toolSlugs: $toolSlugs,
                tags: $tags,
                search: $search,
                authConfigIds: $authConfigIds,
                important: $important,
                scopes: $scopes,
                includeDeprecated: $includeDeprecated,
                toolkitVersions: $toolkitVersions,
                limit: $limit,
            ),
        );

        return array_merge($remote, $this->customPrismTools());
    }

    public function getTool(string $toolSlug): PrismTool
    {
        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToPrism($this->customTools->get($toolSlug));
        }

        $response = $this->toolsApi->getToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $this->prismConverter()->convert($response);
    }

    /**
     * @return Tool[]
     */
    public function getLaravelAiTools(
        ?string $toolkitSlug = null,
        ?array $toolSlugs = null,
        ?array $tags = null,
        ?string $search = null,
        ?array $authConfigIds = null,
        ?bool $important = null,
        ?array $scopes = null,
        bool $includeDeprecated = true,
        mixed $toolkitVersions = null,
        ?int $limit = null,
    ): array {
        $converter = $this->laravelAiConverter();

        $remote = array_map(
            $converter->convert(...),
            $this->fetchTools(
                toolkitSlug: $toolkitSlug,
                toolSlugs: $toolSlugs,
                tags: $tags,
                search: $search,
                authConfigIds: $authConfigIds,
                important: $important,
                scopes: $scopes,
                includeDeprecated: $includeDeprecated,
                toolkitVersions: $toolkitVersions,
                limit: $limit,
            ),
        );

        return array_merge($remote, $this->customLaravelAiTools());
    }

    public function getLaravelAiTool(string $toolSlug): mixed
    {
        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToLaravelAi($this->customTools->get($toolSlug));
        }

        $response = $this->toolsApi->getToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $this->laravelAiConverter()->convert($response);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->executeCustomTool($this->customTools->get($toolSlug), $arguments);
        }

        return $this->executor->execute(
            toolSlug: $toolSlug,
            arguments: $arguments,
            userId: $this->userId,
            connectedAccountId: $this->connectedAccountId,
            version: $this->version,
        );
    }

    public function forUser(string $userId): self
    {
        return new self(
            toolsApi: $this->toolsApi,
            prismConverterFactory: $this->prismConverterFactory,
            laravelAiConverterFactory: $this->laravelAiConverterFactory,
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            schemaMapper: $this->schemaMapper,
            laravelAiSchemaMapper: $this->laravelAiSchemaMapper,
            userId: $userId,
            connectedAccountId: $this->connectedAccountId,
            version: $this->version,
        );
    }

    public function withConnectedAccount(string $connectedAccountId): self
    {
        return new self(
            toolsApi: $this->toolsApi,
            prismConverterFactory: $this->prismConverterFactory,
            laravelAiConverterFactory: $this->laravelAiConverterFactory,
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            schemaMapper: $this->schemaMapper,
            laravelAiSchemaMapper: $this->laravelAiSchemaMapper,
            userId: $this->userId,
            connectedAccountId: $connectedAccountId,
            version: $this->version,
        );
    }

    public function withVersion(string $version): self
    {
        return new self(
            toolsApi: $this->toolsApi,
            prismConverterFactory: $this->prismConverterFactory,
            laravelAiConverterFactory: $this->laravelAiConverterFactory,
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            schemaMapper: $this->schemaMapper,
            laravelAiSchemaMapper: $this->laravelAiSchemaMapper,
            userId: $this->userId,
            connectedAccountId: $this->connectedAccountId,
            version: $version,
        );
    }

    public function hooks(): HookManager
    {
        return $this->hooks;
    }

    public function customTools(): ?CustomToolRegistry
    {
        return $this->customTools;
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

    /**
     * @return array<int, ComposioToolModel>
     */
    private function fetchTools(
        ?string $toolkitSlug,
        ?array $toolSlugs,
        ?array $tags,
        ?string $search,
        ?array $authConfigIds,
        ?bool $important,
        ?array $scopes,
        bool $includeDeprecated,
        mixed $toolkitVersions,
        ?int $limit,
    ): array {
        $allTools = [];
        $cursor = null;

        do {
            $response = $this->toolsApi->getTools(
                toolkit_slug: $toolkitSlug,
                tool_slugs: $this->csv($toolSlugs),
                auth_config_ids: $this->mixedValue($authConfigIds),
                important: $important === null ? null : ($important ? 'true' : 'false'),
                tags: $tags,
                scopes: $scopes,
                search: $search,
                include_deprecated: $includeDeprecated,
                toolkit_versions: $toolkitVersions,
                limit: $limit === null ? null : (float) $limit,
                cursor: $cursor,
            );

            if ($response instanceof Error) {
                throw new ComposioException('Failed to fetch tools: '.$response->getError());
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

    private function mixedValue(mixed $value): mixed
    {
        return $value;
    }

    private function scopedExecutor(): ScopedToolExecutor
    {
        return new ScopedToolExecutor(
            $this->executor,
            $this->userId,
            $this->connectedAccountId,
            $this->version,
        );
    }

    private function prismConverter(): PrismToolConverter
    {
        if ($this->prismConverterFactory === null) {
            throw new ComposioException(
                'PrismPHP is not available. Install it with: composer require prism-php/prism'
            );
        }

        return ($this->prismConverterFactory)($this->scopedExecutor());
    }

    private function laravelAiConverter(): LaravelAiToolConverter
    {
        if ($this->laravelAiConverterFactory === null) {
            throw new ComposioException(
                'Laravel AI is not available. Install it with: composer require laravel/ai'
            );
        }

        return ($this->laravelAiConverterFactory)($this->scopedExecutor());
    }

    /**
     * @return PrismTool[]
     */
    private function customPrismTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map($this->customToPrism(...), $this->customTools->all());
    }

    /**
     * @return Tool[]
     */
    private function customLaravelAiTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map($this->customToLaravelAi(...), $this->customTools->all());
    }

    private function customToPrism(CustomTool $custom): PrismTool
    {
        if ($this->schemaMapper === null) {
            throw new ComposioException('SchemaMapper is required to expose custom tools to PrismPHP.');
        }

        $tool = (new PrismTool)->as($custom->slug)->for($custom->description);

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

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function executeCustomTool(CustomTool $custom, array $arguments): ExecutionResult
    {
        $arguments = $this->hooks->runBefore($custom->slug, $arguments);

        try {
            $output = $custom->execute($arguments);
            $result = ExecutionResult::synthetic(successful: true, data: $this->decodeOutput($output));
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
