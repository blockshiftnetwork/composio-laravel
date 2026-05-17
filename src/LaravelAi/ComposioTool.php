<?php

namespace BlockshiftNetwork\ComposioLaravel\LaravelAi;

use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutorInterface;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ComposioTool implements Tool
{
    public function __construct(
        private readonly ComposioToolModel $composioTool,
        private readonly LaravelAiSchemaMapper $schemaMapper,
        private readonly ToolExecutorInterface $executor,
    ) {}

    public function description(): string
    {
        return $this->composioTool->getDescription();
    }

    public function schema(JsonSchema $schema): array
    {
        /** @var mixed $inputParams */
        $inputParams = $this->composioTool->getInputParameters();

        if ((! is_array($inputParams) && ! is_object($inputParams)) || $inputParams === []) {
            return [];
        }

        return $this->schemaMapper->mapProperties($schema, $inputParams);
    }

    public function handle(Request $request): string
    {
        $result = $this->executor->execute(
            $this->composioTool->getSlug(),
            $request->all(),
        );

        return $result->toToolOutput();
    }
}
