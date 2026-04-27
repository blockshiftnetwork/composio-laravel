<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\LaravelAi;

use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CustomLaravelAiTool implements Tool
{
    public function __construct(
        private readonly CustomTool $tool,
        private readonly LaravelAiSchemaMapper $schemaMapper,
    ) {}

    public function description(): string
    {
        return $this->tool->description;
    }

    public function schema(JsonSchema $schema): array
    {
        if ($this->tool->inputSchema === []) {
            return [];
        }

        return $this->schemaMapper->mapProperties($schema, $this->tool->inputSchema);
    }

    public function handle(Request $request): string
    {
        return $this->tool->execute($request->all());
    }
}
