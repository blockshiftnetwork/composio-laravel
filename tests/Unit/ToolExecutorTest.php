<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteByToolSlug200Response;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ToolExecutionException;
use BlockshiftNetwork\ComposioLaravel\Execution\ExecutionResult;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use Mockery;
use PHPUnit\Framework\TestCase;

class ToolExecutorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_executes_tool_successfully(): void
    {
        $response = Mockery::mock(PostToolsExecuteByToolSlug200Response::class);
        $response->shouldReceive('getSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn(['issue_number' => 42]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlug')
            ->once()
            ->andReturn($response);

        $executor = new ToolExecutor($toolsApi, new HookManager);
        $result = $executor->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test'], 'user_123');

        $this->assertInstanceOf(ExecutionResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(['issue_number' => 42], $result->data());
        $this->assertNull($result->error());
        $this->assertEquals('log_123', $result->logId());
    }

    public function test_throws_exception_on_error_response(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('Unauthorized');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlug')
            ->once()
            ->andReturn($error);

        $executor = new ToolExecutor($toolsApi, new HookManager);

        $this->expectException(ToolExecutionException::class);
        $this->expectExceptionMessage("Tool execution failed for 'GITHUB_CREATE_ISSUE'");

        $executor->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test']);
    }

    public function test_runs_before_hooks(): void
    {
        $response = Mockery::mock(PostToolsExecuteByToolSlug200Response::class);
        $response->shouldReceive('getSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn([]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlug')
            ->once()
            ->withArgs(function ($slug, $request) {
                return $request->getArguments()['extra'] === 'added_by_hook';
            })
            ->andReturn($response);

        $hookManager = new HookManager;
        $hookManager->beforeExecute('GITHUB_CREATE_ISSUE', function (string $tool, array $args) {
            $args['extra'] = 'added_by_hook';

            return $args;
        });

        $executor = new ToolExecutor($toolsApi, $hookManager);
        $result = $executor->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test']);

        $this->assertTrue($result->isSuccessful());
    }

    public function test_runs_after_hooks(): void
    {
        $response = Mockery::mock(PostToolsExecuteByToolSlug200Response::class);
        $response->shouldReceive('getSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn(['key' => 'value']);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlug')
            ->once()
            ->andReturn($response);

        $hookCalled = false;
        $hookManager = new HookManager;
        $hookManager->afterExecute('*', function (string $tool, ExecutionResult $result) use (&$hookCalled) {
            $hookCalled = true;

            return $result;
        });

        $executor = new ToolExecutor($toolsApi, $hookManager);
        $executor->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test']);

        $this->assertTrue($hookCalled);
    }

    public function test_wildcard_hooks_run_for_all_tools(): void
    {
        $response = Mockery::mock(PostToolsExecuteByToolSlug200Response::class);
        $response->shouldReceive('getSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn([]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlug')
            ->andReturn($response);

        $toolsExecuted = [];
        $hookManager = new HookManager;
        $hookManager->beforeExecute('*', function (string $tool, array $args) use (&$toolsExecuted) {
            $toolsExecuted[] = $tool;

            return $args;
        });

        $executor = new ToolExecutor($toolsApi, $hookManager);
        $executor->execute('GITHUB_CREATE_ISSUE', []);
        $executor->execute('SLACK_SEND_MESSAGE', []);

        $this->assertEquals(['GITHUB_CREATE_ISSUE', 'SLACK_SEND_MESSAGE'], $toolsExecuted);
    }
}
