<?php

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlug200Response;

class ExecutionResult
{
    public function __construct(
        private PostToolsExecuteByToolSlug200Response $response,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->response->getSuccessful();
    }

    public function data(): array
    {
        return $this->response->getData() ?? [];
    }

    public function error(): ?string
    {
        return $this->response->getError();
    }

    public function logId(): string
    {
        return $this->response->getLogId();
    }

    public function toToolOutput(): string
    {
        if (! $this->isSuccessful()) {
            return json_encode(['error' => $this->error()]);
        }

        return json_encode($this->data());
    }

    public function raw(): PostToolsExecuteByToolSlug200Response
    {
        return $this->response;
    }
}
