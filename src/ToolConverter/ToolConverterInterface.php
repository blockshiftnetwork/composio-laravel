<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use BlockshiftNetwork\Composio\Model\Tool;

interface ToolConverterInterface
{
    public function convert(
        Tool $composioTool,
        ?string $userId = null,
        ?string $entityId = null,
        ?string $connectedAccountId = null,
    ): mixed;
}
