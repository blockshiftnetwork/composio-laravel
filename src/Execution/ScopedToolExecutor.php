<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Execution;

class ScopedToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolExecutor $executor,
        private readonly ?string $userId = null,
        private readonly ?string $connectedAccountId = null,
        private readonly ?string $version = null,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        return $this->executor->execute(
            toolSlug: $toolSlug,
            arguments: $arguments,
            userId: $this->userId,
            connectedAccountId: $this->connectedAccountId,
            version: $this->version,
        );
    }
}
