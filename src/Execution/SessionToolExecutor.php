<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Api\ToolRouterApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolRouterSessionBySessionIdExecuteRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ToolExecutionException;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;

class SessionToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolRouterApi $toolRouterApi,
        private readonly HookManager $hooks,
        private readonly string $sessionId,
        private readonly ?string $sessionAccessKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolSlug, array $arguments = []): ExecutionResult
    {
        $arguments = $this->hooks->runBefore($toolSlug, $arguments);

        $request = new PostToolRouterSessionBySessionIdExecuteRequest;
        $request->setToolSlug($toolSlug);
        $request->setArguments($this->argumentsPayload($arguments));

        $response = $this->toolRouterApi->postToolRouterSessionBySessionIdExecute(
            $this->sessionId,
            $this->sessionAccessKey,
            $request,
        );

        if ($response instanceof Error) {
            throw new ToolExecutionException(
                "Session tool execution failed for '{$toolSlug}': ".$response->getError()
            );
        }

        $result = ExecutionResult::fromSessionResponse($response);

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
