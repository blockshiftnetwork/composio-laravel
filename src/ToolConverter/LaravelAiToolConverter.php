<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\LaravelAi\ComposioTool;

class LaravelAiToolConverter implements ToolConverterInterface
{
    public function __construct(
        private readonly LaravelAiSchemaMapper $schemaMapper,
        private readonly ToolExecutor $executor,
    ) {}

    public function convert(
        ComposioToolModel $composioTool,
        ?string $userId = null,
        ?string $entityId = null,
        ?string $connectedAccountId = null,
    ): ComposioTool {
        return new ComposioTool(
            composioTool: $composioTool,
            schemaMapper: $this->schemaMapper,
            executor: $this->executor,
            userId: $userId,
            entityId: $entityId,
            connectedAccountId: $connectedAccountId,
        );
    }
}
