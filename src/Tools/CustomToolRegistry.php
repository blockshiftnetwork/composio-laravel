<?php

namespace BlockshiftNetwork\ComposioLaravel\Tools;

use Closure;

class CustomToolRegistry
{
    /** @var array<string, CustomTool> */
    private array $tools = [];

    /**
     * @param  array<string, mixed>  $inputSchema  JSON Schema (object with `properties` and optional `required`).
     * @param  Closure(array<string, mixed>): (array<mixed>|string)  $handler
     */
    public function register(string $slug, string $description, array $inputSchema, Closure $handler): self
    {
        $this->tools[$slug] = new CustomTool($slug, $description, $inputSchema, $handler);

        return $this;
    }

    public function has(string $slug): bool
    {
        return isset($this->tools[$slug]);
    }

    public function get(string $slug): ?CustomTool
    {
        return $this->tools[$slug] ?? null;
    }

    /** @return array<CustomTool> */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function unregister(string $slug): void
    {
        unset($this->tools[$slug]);
    }
}
