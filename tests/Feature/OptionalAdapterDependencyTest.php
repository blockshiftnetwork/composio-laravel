<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Feature;

use BlockshiftNetwork\Composio\Api\ToolRouterApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\SessionToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\Session\ComposioSession;
use BlockshiftNetwork\ComposioLaravel\Support\OptionalDependencyChecker;
use BlockshiftNetwork\ComposioLaravel\Tools\ToolManager;
use Mockery;
use PHPUnit\Framework\TestCase;

class OptionalAdapterDependencyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_core_managers_boot_without_autoloading_optional_adapters(): void
    {
        $output = [];
        $exitCode = 1;

        exec(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Fixtures/boot_without_optional_adapters.php').' 2>&1',
            $output,
            $exitCode,
        );

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
    }

    public function test_prism_tool_manager_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $manager = $this->makeToolManager($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('PrismPHP is not available. Install it with: composer require prism-php/prism');

        $manager->get(limit: 1);
    }

    public function test_laravel_ai_tool_manager_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $manager = $this->makeToolManager($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Laravel AI is not available. Install it with: composer require laravel/ai');

        $manager->getLaravelAiTools(limit: 1);
    }

    public function test_single_prism_tool_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $manager = $this->makeToolManager($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('PrismPHP is not available. Install it with: composer require prism-php/prism');

        $manager->getTool('GITHUB_CREATE_ISSUE');
    }

    public function test_single_laravel_ai_tool_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $manager = $this->makeToolManager($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Laravel AI is not available. Install it with: composer require laravel/ai');

        $manager->getLaravelAiTool('GITHUB_CREATE_ISSUE');
    }

    public function test_prism_session_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $session = $this->makeSession($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('PrismPHP is not available. Install it with: composer require prism-php/prism');

        $session->tools();
    }

    public function test_laravel_ai_session_entrypoint_throws_clear_exception_when_dependency_is_missing(): void
    {
        $session = $this->makeSession($this->missingOptionalDependencies());

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Laravel AI is not available. Install it with: composer require laravel/ai');

        $session->laravelAiTools();
    }

    private function makeToolManager(OptionalDependencyChecker $optionalDependencies): ToolManager
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $hooks = new HookManager;
        $executor = new ToolExecutor($toolsApi, $hooks);

        return new ToolManager(
            toolsApi: $toolsApi,
            executor: $executor,
            hooks: $hooks,
            optionalDependencies: $optionalDependencies,
        );
    }

    private function makeSession(OptionalDependencyChecker $optionalDependencies): ComposioSession
    {
        $routerApi = Mockery::mock(ToolRouterApi::class);
        $hooks = new HookManager;
        $executor = new SessionToolExecutor($routerApi, $hooks, 'session_123');

        return new ComposioSession(
            toolRouterApi: $routerApi,
            toolsApi: Mockery::mock(ToolsApi::class),
            executor: $executor,
            sessionId: 'session_123',
            toolSlugs: ['GITHUB_CREATE_ISSUE'],
            optionalDependencies: $optionalDependencies,
        );
    }

    private function missingOptionalDependencies(): OptionalDependencyChecker
    {
        return new OptionalDependencyChecker(
            classExists: fn (string $class): bool => false,
            interfaceExists: fn (string $interface): bool => false,
        );
    }
}
