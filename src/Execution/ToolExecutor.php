<?php

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlugRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ToolExecutionException;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;

class ToolExecutor
{
    public function __construct(
        private readonly ToolsApi $toolsApi,
        private readonly HookManager $hooks,
    ) {}

    public function execute(
        string $toolSlug,
        array $arguments,
        ?string $userId = null,
        ?string $entityId = null,
        ?string $connectedAccountId = null,
    ): ExecutionResult {
        $arguments = $this->hooks->runBefore($toolSlug, $arguments);

        $request = new PostToolsExecuteByToolSlugRequest;
        $request->setArguments($arguments);

        if ($userId !== null) {
            $request->setUserId($userId);
        }

        if ($entityId !== null) {
            $request->setEntityId($entityId);
        }

        if ($connectedAccountId !== null) {
            $request->setConnectedAccountId($connectedAccountId);
        }

        $response = $this->toolsApi->postToolsExecuteByToolSlug($toolSlug, $request);

        if ($response instanceof Error) {
            throw new ToolExecutionException(
                "Tool execution failed for '{$toolSlug}': ".$response->getError()
            );
        }

        $result = new ExecutionResult($response);

        return $this->hooks->runAfter($toolSlug, $result);
    }
}
