<?php

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlug200Response;

class ExecutionResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private readonly bool $successful,
        private readonly array $data,
        private readonly ?string $error,
        private readonly ?string $logId,
        private readonly ?PostToolsExecuteByToolSlug200Response $response = null,
    ) {}

    public static function fromResponse(PostToolsExecuteByToolSlug200Response $response): self
    {
        return new self(
            successful: (bool) $response->getSuccessful(),
            data: $response->getData() ?? [],
            error: $response->getError(),
            logId: $response->getLogId() ?? '',
            response: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function synthetic(bool $successful, array $data = [], ?string $error = null, ?string $logId = null): self
    {
        return new self(
            successful: $successful,
            data: $data,
            error: $error,
            logId: $logId ?? '',
        );
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function logId(): string
    {
        return $this->logId ?? '';
    }

    public function toToolOutput(): string
    {
        if (! $this->isSuccessful()) {
            return json_encode(['error' => $this->error]);
        }

        return json_encode($this->data);
    }

    public function raw(): ?PostToolsExecuteByToolSlug200Response
    {
        return $this->response;
    }
}
