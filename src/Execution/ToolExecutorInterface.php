<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Execution;

interface ToolExecutorInterface
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolSlug, array $arguments = []): ExecutionResult;
}
