<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use Prism\Prism\Tool;

class PrismToolConverter implements ToolConverterInterface
{
    public function __construct(
        private SchemaMapper $schemaMapper,
        private ToolExecutor $executor,
    ) {}

    public function convert(
        ComposioTool $composioTool,
        ?string $userId = null,
        ?string $entityId = null,
        ?string $connectedAccountId = null,
    ): Tool {
        $slug = $composioTool->getSlug();

        $tool = (new Tool)
            ->as($slug)
            ->for($composioTool->getDescription());

        $inputParams = $composioTool->getInputParameters();
        if (is_array($inputParams) && ! empty($inputParams)) {
            $tool = $this->schemaMapper->applySchema($tool, $inputParams);
        }

        $executor = $this->executor;
        $tool = $tool->using(function () use ($executor, $slug, $userId, $entityId, $connectedAccountId) {
            $arguments = func_get_args();

            // PrismPHP passes named arguments as key-value pairs
            $namedArgs = [];
            if (count($arguments) === 1 && is_array($arguments[0])) {
                $namedArgs = $arguments[0];
            } else {
                $namedArgs = $arguments;
            }

            $result = $executor->execute($slug, $namedArgs, $userId, $entityId, $connectedAccountId);

            return $result->toToolOutput();
        });

        return $tool;
    }
}
