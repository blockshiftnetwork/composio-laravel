<?php

namespace BlockshiftNetwork\ComposioLaravel\Execution;

use BlockshiftNetwork\Composio\Model\PostV31ToolRouterSessionBySessionIdExecute200Response;
use BlockshiftNetwork\Composio\Model\PostV31ToolsExecuteByToolSlug200Response;

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
        private readonly mixed $response = null,
    ) {}

    public static function fromResponse(PostV31ToolsExecuteByToolSlug200Response $response): self
    {
        return new self(
            successful: (bool) $response->getSuccessful(),
            data: self::normalizeData($response->getData()),
            error: self::normalizeError($response->getError()),
            logId: self::normalizeLogId($response->getLogId()),
            response: $response,
        );
    }

    public static function fromSessionResponse(PostV31ToolRouterSessionBySessionIdExecute200Response $response): self
    {
        /** @var mixed $data */
        $data = $response->getData();
        $error = $response->getError();
        /** @var mixed $logId */
        $logId = $response->getLogId();

        return new self(
            successful: $error === null || $error === '',
            data: self::normalizeData($data),
            error: self::normalizeError($error),
            logId: self::normalizeLogId($logId),
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

    public function raw(): mixed
    {
        return $this->response;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeData(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }

    private static function normalizeError(mixed $error): ?string
    {
        return is_string($error) && $error !== '' ? $error : null;
    }

    private static function normalizeLogId(mixed $logId): string
    {
        return is_string($logId) ? $logId : '';
    }
}
