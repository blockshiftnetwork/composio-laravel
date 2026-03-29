<?php

namespace BlockshiftNetwork\ComposioLaravel\Hooks;

use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use Closure;

class HookManager
{
    /** @var array<string, list<Closure|BeforeExecuteHook>> */
    private array $beforeHooks = [];

    /** @var array<string, list<Closure|AfterExecuteHook>> */
    private array $afterHooks = [];

    public function beforeExecute(string|array $tools, Closure|BeforeExecuteHook $hook): void
    {
        $tools = is_array($tools) ? $tools : [$tools];

        foreach ($tools as $tool) {
            $this->beforeHooks[$tool][] = $hook;
        }
    }

    public function afterExecute(string|array $tools, Closure|AfterExecuteHook $hook): void
    {
        $tools = is_array($tools) ? $tools : [$tools];

        foreach ($tools as $tool) {
            $this->afterHooks[$tool][] = $hook;
        }
    }

    public function runBefore(string $toolSlug, array $arguments): array
    {
        $hooks = array_merge(
            $this->beforeHooks['*'] ?? [],
            $this->beforeHooks[$toolSlug] ?? [],
        );

        foreach ($hooks as $hook) {
            if ($hook instanceof BeforeExecuteHook) {
                $arguments = $hook->handle($toolSlug, $arguments);
            } else {
                $arguments = $hook($toolSlug, $arguments);
            }
        }

        return $arguments;
    }

    public function runAfter(string $toolSlug, ExecutionResult $result): ExecutionResult
    {
        $hooks = array_merge(
            $this->afterHooks['*'] ?? [],
            $this->afterHooks[$toolSlug] ?? [],
        );

        foreach ($hooks as $hook) {
            if ($hook instanceof AfterExecuteHook) {
                $result = $hook->handle($toolSlug, $result);
            } else {
                $result = $hook($toolSlug, $result);
            }
        }

        return $result;
    }
}
