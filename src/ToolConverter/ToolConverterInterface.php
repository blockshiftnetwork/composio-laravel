<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool;

interface ToolConverterInterface
{
    public function convert(Tool $composioTool): mixed;
}
