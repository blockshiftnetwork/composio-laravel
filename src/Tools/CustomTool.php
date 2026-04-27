<?php

namespace BlockshiftNetwork\ComposioLaravel\Tools;

use Closure;

class CustomTool
{
    /**
     * @param  array<string, mixed>  $inputSchema
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly Closure $handler,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments): string
    {
        $result = ($this->handler)($arguments);

        if (is_string($result)) {
            return $result;
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }
}
