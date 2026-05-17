<?php

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostV31ToolsExecuteByToolSlugRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ToolExecutionException;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;

class ToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolsApi $toolsApi,
        private readonly HookManager $hooks,
    ) {}

    public function execute(
        string $toolSlug,
        array $arguments = [],
        ?string $userId = null,
        ?string $connectedAccountId = null,
        ?string $version = null,
    ): ExecutionResult {
        if ($userId === null) {
            throw new ComposioException(
                'A Composio user ID is required. Call Composio::tools($userId) or $toolManager->forUser($userId).'
            );
        }

        $arguments = $this->hooks->runBefore($toolSlug, $arguments);

        $request = new PostV31ToolsExecuteByToolSlugRequest;
        $request->setArguments($this->argumentsPayload($arguments));

        $request->setUserId($userId);

        if ($connectedAccountId !== null) {
            $request->setConnectedAccountId($connectedAccountId);
        }

        if ($version !== null) {
            $request->setVersion($version);
        }

        $response = $this->toolsApi->postV31ToolsExecuteByToolSlug($toolSlug, null, $request);

        if ($response instanceof Error) {
            throw new ToolExecutionException(
                "Tool execution failed for '{$toolSlug}': ".$response->getError()
            );
        }

        $result = ExecutionResult::fromResponse($response);

        return $this->hooks->runAfter($toolSlug, $result);
    }

    /**
     * Composio validates arguments as a JSON object. In PHP, an empty array
     * serializes to [] instead of {}, so send stdClass only for that case.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function argumentsPayload(array $arguments): array|object
    {
        return $arguments === [] ? new \stdClass : $arguments;
    }
}
