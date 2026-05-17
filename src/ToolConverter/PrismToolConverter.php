<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutorInterface;
use Prism\Prism\Tool;

class PrismToolConverter implements ToolConverterInterface
{
    public function __construct(
        private readonly SchemaMapper $schemaMapper,
        private readonly ToolExecutorInterface $executor,
    ) {}

    public function convert(ComposioTool $composioTool): Tool
    {
        $slug = $composioTool->getSlug();

        $tool = (new Tool)
            ->as($slug)
            ->for($composioTool->getDescription());

        /** @var mixed $inputParams */
        $inputParams = $composioTool->getInputParameters();
        if ((is_array($inputParams) || is_object($inputParams)) && $inputParams !== []) {
            $tool = $this->schemaMapper->applySchema($tool, $inputParams);
        }

        $executor = $this->executor;
        $tool = $tool->using(function () use ($executor, $slug): string {
            $arguments = func_get_args();
            $namedArgs = count($arguments) === 1 && is_array($arguments[0]) ? $arguments[0] : $arguments;

            $result = $executor->execute($slug, $namedArgs);

            return $result->toToolOutput();
        });

        return $tool;
    }
}
