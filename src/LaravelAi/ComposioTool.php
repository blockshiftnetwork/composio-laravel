<?php

namespace BlockshiftNetwork\ComposioLaravel\LaravelAi;

use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ComposioTool implements Tool
{
    public function __construct(
        private readonly ComposioToolModel $composioTool,
        private readonly LaravelAiSchemaMapper $schemaMapper,
        private readonly ToolExecutor $executor,
        private readonly ?string $userId = null,
        private readonly ?string $entityId = null,
        private readonly ?string $connectedAccountId = null,
    ) {}

    public function description(): string
    {
        return $this->composioTool->getDescription();
    }

    public function schema(JsonSchema $schema): array
    {
        $inputParams = $this->composioTool->getInputParameters();

        if (! is_array($inputParams) || $inputParams === []) {
            return [];
        }

        return $this->schemaMapper->mapProperties($schema, $inputParams);
    }

    public function handle(Request $request): string
    {
        $result = $this->executor->execute(
            $this->composioTool->getSlug(),
            $request->all(),
            $this->userId,
            $this->entityId,
            $this->connectedAccountId,
        );

        return $result->toToolOutput();
    }
}
