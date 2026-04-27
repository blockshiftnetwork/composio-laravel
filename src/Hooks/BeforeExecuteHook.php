<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Hooks;

interface BeforeExecuteHook
{
    public function handle(string $toolSlug, array $arguments): array;
}
