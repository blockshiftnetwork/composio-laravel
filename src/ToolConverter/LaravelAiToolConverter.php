<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutorInterface;
use BlockshiftNetwork\ComposioLaravel\LaravelAi\ComposioTool;

class LaravelAiToolConverter implements ToolConverterInterface
{
    public function __construct(
        private readonly LaravelAiSchemaMapper $schemaMapper,
        private readonly ToolExecutorInterface $executor,
    ) {}

    public function convert(ComposioToolModel $composioTool): ComposioTool
    {
        return new ComposioTool(
            composioTool: $composioTool,
            schemaMapper: $this->schemaMapper,
            executor: $this->executor,
        );
    }
}
