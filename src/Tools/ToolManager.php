<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tools;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostV31ToolsExecuteByToolSlugInputRequest;
use BlockshiftNetwork\Composio\Model\PostV31ToolsExecuteProxyRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\ScopedToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\Support\OptionalDependencyChecker;

class ToolManager
{
    private const string PRISM_CONVERTER_CLASS = 'BlockshiftNetwork\\ComposioLaravel\\ToolConverter\\PrismToolConverter';

    private const string PRISM_SCHEMA_MAPPER_CLASS = 'BlockshiftNetwork\\ComposioLaravel\\ToolConverter\\SchemaMapper';

    private const string LARAVEL_AI_CONVERTER_CLASS = 'BlockshiftNetwork\\ComposioLaravel\\ToolConverter\\LaravelAiToolConverter';

    private const string LARAVEL_AI_SCHEMA_MAPPER_CLASS = 'BlockshiftNetwork\\ComposioLaravel\\ToolConverter\\LaravelAiSchemaMapper';

    private const string CUSTOM_LARAVEL_AI_TOOL_CLASS = 'BlockshiftNetwork\\ComposioLaravel\\LaravelAi\\CustomLaravelAiTool';

    private readonly OptionalDependencyChecker $optionalDependencies;

    public function __construct(
        private readonly ToolsApi $toolsApi,
        private readonly ToolExecutor $executor,
        private readonly HookManager $hooks,
        private readonly ?CustomToolRegistry $customTools = null,
        ?OptionalDependencyChecker $optionalDependencies = null,
        private readonly ?string $userId = null,
        private readonly ?string $connectedAccountId = null,
        private readonly ?string $version = null,
    ) {
        $this->optionalDependencies = $optionalDependencies ?? new OptionalDependencyChecker;
    }

    /**
     * @return array<int, mixed>
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

    public function getTool(string $toolSlug): mixed
    {
        $converter = $this->prismConverter();

        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToPrism($this->customTools->get($toolSlug));
        }

        $response = $this->toolsApi->getV31ToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $converter->convert($response);
    }

    /**
     * @return array<int, mixed>
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
        $converter = $this->laravelAiConverter();

        if ($this->customTools !== null && $this->customTools->has($toolSlug)) {
            return $this->customToLaravelAi($this->customTools->get($toolSlug));
        }

        $response = $this->toolsApi->getV31ToolsByToolSlug($toolSlug);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to fetch tool '{$toolSlug}': ".$response->getError());
        }

        return $converter->convert($response);
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
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            optionalDependencies: $this->optionalDependencies,
            userId: $userId,
            connectedAccountId: $this->connectedAccountId,
            version: $this->version,
        );
    }

    public function withConnectedAccount(string $connectedAccountId): self
    {
        return new self(
            toolsApi: $this->toolsApi,
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            optionalDependencies: $this->optionalDependencies,
            userId: $this->userId,
            connectedAccountId: $connectedAccountId,
            version: $this->version,
        );
    }

    public function withVersion(string $version): self
    {
        return new self(
            toolsApi: $this->toolsApi,
            executor: $this->executor,
            hooks: $this->hooks,
            customTools: $this->customTools,
            optionalDependencies: $this->optionalDependencies,
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
        $response = $this->toolsApi->getV31ToolsEnum();

        if ($response instanceof Error) {
            throw new ComposioException('Failed to fetch tool enums: '.$response->getError());
        }

        return $response;
    }

    public function generateInputs(string $toolSlug, string $text): mixed
    {
        $request = new PostV31ToolsExecuteByToolSlugInputRequest;
        $request->setText($text);

        $response = $this->toolsApi->postV31ToolsExecuteByToolSlugInput($toolSlug, $request);

        if ($response instanceof Error) {
            throw new ComposioException("Failed to generate inputs for '{$toolSlug}': ".$response->getError());
        }

        return $response;
    }

    public function proxyExecute(PostV31ToolsExecuteProxyRequest $request): mixed
    {
        $response = $this->toolsApi->postV31ToolsExecuteProxy($request);

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
            $response = $this->toolsApi->getV31Tools(
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

    private function prismConverter(): mixed
    {
        $this->ensurePrismAvailable();

        $converterClass = self::PRISM_CONVERTER_CLASS;
        $schemaMapperClass = self::PRISM_SCHEMA_MAPPER_CLASS;

        return new $converterClass(new $schemaMapperClass, $this->scopedExecutor());
    }

    private function laravelAiConverter(): mixed
    {
        $this->ensureLaravelAiAvailable();

        $converterClass = self::LARAVEL_AI_CONVERTER_CLASS;
        $schemaMapperClass = self::LARAVEL_AI_SCHEMA_MAPPER_CLASS;

        return new $converterClass(new $schemaMapperClass, $this->scopedExecutor());
    }

    /**
     * @return array<int, mixed>
     */
    private function customPrismTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map($this->customToPrism(...), $this->customTools->all());
    }

    /**
     * @return array<int, mixed>
     */
    private function customLaravelAiTools(): array
    {
        if ($this->customTools === null) {
            return [];
        }

        return array_map($this->customToLaravelAi(...), $this->customTools->all());
    }

    private function customToPrism(CustomTool $custom): mixed
    {
        $this->ensurePrismAvailable();

        $toolClass = OptionalDependencyChecker::PRISM_TOOL_CLASS;
        $tool = (new $toolClass)->as($custom->slug)->for($custom->description);

        if ($custom->inputSchema !== []) {
            $tool = $this->prismSchemaMapper()->applySchema($tool, $custom->inputSchema);
        }

        return $tool->using(function () use ($custom): string {
            $arguments = func_get_args();
            $namedArgs = count($arguments) === 1 && is_array($arguments[0]) ? $arguments[0] : $arguments;

            return $custom->execute($namedArgs);
        });
    }

    private function customToLaravelAi(CustomTool $custom): mixed
    {
        $this->ensureLaravelAiAvailable();

        $customToolClass = self::CUSTOM_LARAVEL_AI_TOOL_CLASS;

        return new $customToolClass($custom, $this->laravelAiSchemaMapper());
    }

    private function prismSchemaMapper(): mixed
    {
        $schemaMapperClass = self::PRISM_SCHEMA_MAPPER_CLASS;

        return new $schemaMapperClass;
    }

    private function laravelAiSchemaMapper(): mixed
    {
        $schemaMapperClass = self::LARAVEL_AI_SCHEMA_MAPPER_CLASS;

        return new $schemaMapperClass;
    }

    private function ensurePrismAvailable(): void
    {
        if (! $this->optionalDependencies->prismAvailable()) {
            throw new ComposioException(
                'PrismPHP is not available. Install it with: composer require prism-php/prism'
            );
        }
    }

    private function ensureLaravelAiAvailable(): void
    {
        if (! $this->optionalDependencies->laravelAiAvailable()) {
            throw new ComposioException(
                'Laravel AI is not available. Install it with: composer require laravel/ai'
            );
        }
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
