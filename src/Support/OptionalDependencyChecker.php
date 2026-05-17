<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Support;

final readonly class OptionalDependencyChecker
{
    public const string PRISM_TOOL_CLASS = 'Prism\\Prism\\Tool';

    public const string LARAVEL_AI_TOOL_CONTRACT = 'Laravel\\Ai\\Contracts\\Tool';

    /**
     * @param  (callable(string): bool)|null  $classExists
     * @param  (callable(string): bool)|null  $interfaceExists
     */
    public function __construct(
        private mixed $classExists = null,
        private mixed $interfaceExists = null,
    ) {}

    public function prismAvailable(): bool
    {
        return $this->classExists(self::PRISM_TOOL_CLASS);
    }

    public function laravelAiAvailable(): bool
    {
        return $this->interfaceExists(self::LARAVEL_AI_TOOL_CONTRACT);
    }

    private function classExists(string $class): bool
    {
        if ($this->classExists !== null) {
            return (bool) ($this->classExists)($class);
        }

        return class_exists($class);
    }

    private function interfaceExists(string $interface): bool
    {
        if ($this->interfaceExists !== null) {
            return (bool) ($this->interfaceExists)($interface);
        }

        return interface_exists($interface);
    }
}
