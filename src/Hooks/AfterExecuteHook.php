<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Hooks;

use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;

interface AfterExecuteHook
{
    public function handle(string $toolSlug, ExecutionResult $result): ExecutionResult;
}
