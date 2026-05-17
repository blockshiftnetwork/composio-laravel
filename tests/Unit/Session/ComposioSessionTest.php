<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Session;

use BlockshiftNetwork\Composio\Api\ToolRouterApi;
use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\PostToolRouterSessionBySessionIdExecute200Response;
use BlockshiftNetwork\Composio\Model\PostToolRouterSessionBySessionIdLink201Response;
use BlockshiftNetwork\Composio\Model\PostToolRouterSessionBySessionIdLinkRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\Composio\Model\ToolsPaginated;
use BlockshiftNetwork\ComposioLaravel\Execution\SessionToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\Session\ComposioSession;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use Laravel\Ai\Contracts\Tool as LaravelAiToolContract;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool as PrismTool;

class ComposioSessionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_executes_tool_through_session_router(): void
    {
        $response = Mockery::mock(PostToolRouterSessionBySessionIdExecute200Response::class);
        $response->shouldReceive('getData')->andReturn(['issue_number' => 42]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $routerApi = Mockery::mock(ToolRouterApi::class);
        $routerApi->shouldReceive('postToolRouterSessionBySessionIdExecute')
            ->once()
            ->withArgs(function (string $sessionId, mixed $accessKey, mixed $request): bool {
                return $sessionId === 'session_123'
                    && $accessKey === null
                    && $request->getToolSlug() === 'GITHUB_CREATE_ISSUE'
                    && $request->getArguments() === ['title' => 'Test'];
            })
            ->andReturn($response);

        $session = $this->makeSession($routerApi, Mockery::mock(ToolsApi::class));

        $result = $session->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['issue_number' => 42], $result->data());
        $this->assertSame('log_123', $result->logId());
    }

    public function test_executes_session_tool_with_empty_arguments_as_json_object(): void
    {
        $response = Mockery::mock(PostToolRouterSessionBySessionIdExecute200Response::class);
        $response->shouldReceive('getData')->andReturn([]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $routerApi = Mockery::mock(ToolRouterApi::class);
        $routerApi->shouldReceive('postToolRouterSessionBySessionIdExecute')
            ->once()
            ->withArgs(function (string $sessionId, mixed $accessKey, mixed $request): bool {
                return $sessionId === 'session_123'
                    && $accessKey === null
                    && $request->getToolSlug() === 'HACKERNEWS_GET_FRONTPAGE'
                    && $request->getArguments() instanceof \stdClass;
            })
            ->andReturn($response);

        $session = $this->makeSession($routerApi, Mockery::mock(ToolsApi::class));

        $this->assertTrue($session->execute('HACKERNEWS_GET_FRONTPAGE')->isSuccessful());
    }

    public function test_exposes_session_tools_as_prism_tools(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')
            ->once()
            ->withArgs(fn (...$args): bool => $args[1] === 'GITHUB_CREATE_ISSUE')
            ->andReturn(new ToolsPaginated([
                'items' => [$this->makeTool('GITHUB_CREATE_ISSUE')],
                'next_cursor' => null,
            ]));

        $session = $this->makeSession(Mockery::mock(ToolRouterApi::class), $toolsApi);

        $tools = $session->tools();

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(PrismTool::class, $tools[0]);
    }

    public function test_exposes_session_tools_as_laravel_ai_tools(): void
    {
        if (! interface_exists(LaravelAiToolContract::class)) {
            $this->markTestSkipped('Laravel AI is not installed');
        }

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')
            ->once()
            ->andReturn(new ToolsPaginated([
                'items' => [$this->makeTool('GITHUB_CREATE_ISSUE')],
                'next_cursor' => null,
            ]));

        $session = $this->makeSession(Mockery::mock(ToolRouterApi::class), $toolsApi);

        $tools = $session->laravelAiTools();

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(LaravelAiToolContract::class, $tools[0]);
    }

    public function test_authorize_links_toolkit_to_session(): void
    {
        $linkResponse = Mockery::mock(PostToolRouterSessionBySessionIdLink201Response::class);
        $linkResponse->shouldReceive('getRedirectUrl')->andReturn('https://composio.test/authorize');

        $routerApi = Mockery::mock(ToolRouterApi::class);
        $routerApi->shouldReceive('postToolRouterSessionBySessionIdLink')
            ->once()
            ->withArgs(function (string $sessionId, mixed $request): bool {
                return $sessionId === 'session_123'
                    && $request instanceof PostToolRouterSessionBySessionIdLinkRequest
                    && $request->getToolkit() === 'github'
                    && $request->getCallbackUrl() === 'https://app.test/callback';
            })
            ->andReturn($linkResponse);

        $session = $this->makeSession($routerApi, Mockery::mock(ToolsApi::class));

        $response = $session->authorize('github', 'https://app.test/callback');

        $this->assertSame('https://composio.test/authorize', $response->getRedirectUrl());
    }

    private function makeSession(ToolRouterApi $routerApi, ToolsApi $toolsApi): ComposioSession
    {
        $hooks = new HookManager;
        $executor = new SessionToolExecutor($routerApi, $hooks, 'session_123');

        return new ComposioSession(
            toolRouterApi: $routerApi,
            toolsApi: $toolsApi,
            executor: $executor,
            prismConverter: new PrismToolConverter(new SchemaMapper, $executor),
            laravelAiConverter: new LaravelAiToolConverter(new LaravelAiSchemaMapper, $executor),
            sessionId: 'session_123',
            mcp: ['type' => 'http', 'url' => 'https://mcp.composio.dev/session_123'],
            toolSlugs: ['GITHUB_CREATE_ISSUE'],
        );
    }

    private function makeTool(string $slug): ComposioToolModel
    {
        return new ComposioToolModel([
            'slug' => $slug,
            'description' => 'Create an issue',
            'input_parameters' => [],
        ]);
    }
}
