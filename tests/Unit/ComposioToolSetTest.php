<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolsExecuteProxyRequest;
use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\Composio\Model\ToolsPaginated;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool;
use stdClass;

class ComposioToolSetTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_tools_returns_prism_tools(): void
    {
        $composioTool = $this->createMockComposioTool('GITHUB_CREATE_ISSUE', 'Create an issue');

        $paginated = Mockery::mock(ToolsPaginated::class);
        $paginated->shouldReceive('getItems')->andReturn([$composioTool]);
        $paginated->shouldReceive('getNextCursor')->andReturn(null);

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')->once()->andReturn($paginated);

        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: $converter,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
            userId: 'user_123',
        );

        $tools = $toolSet->getTools(toolkitSlug: 'github');

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(Tool::class, $tools[0]);
    }

    public function test_get_tools_handles_pagination(): void
    {
        $tool1 = $this->createMockComposioTool('GITHUB_CREATE_ISSUE', 'Create an issue');
        $tool2 = $this->createMockComposioTool('GITHUB_LIST_ISSUES', 'List issues');

        $page1 = Mockery::mock(ToolsPaginated::class);
        $page1->shouldReceive('getItems')->andReturn([$tool1]);
        $page1->shouldReceive('getNextCursor')->andReturn('cursor_page2');

        $page2 = Mockery::mock(ToolsPaginated::class);
        $page2->shouldReceive('getItems')->andReturn([$tool2]);
        $page2->shouldReceive('getNextCursor')->andReturn(null);

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')
            ->twice()
            ->andReturn($page1, $page2);

        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: $converter,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
        );

        $tools = $toolSet->getTools(toolkitSlug: 'github');

        $this->assertCount(2, $tools);
    }

    public function test_get_tools_throws_on_error(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('API error');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')->once()->andReturn($error);

        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: $converter,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
        );

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to fetch tools');

        $toolSet->getTools(toolkitSlug: 'github');
    }

    public function test_for_user_returns_new_instance(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $executor = Mockery::mock(ToolExecutor::class);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: null,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
            userId: 'user_1',
        );

        $scoped = $toolSet->forUser('user_2');

        $this->assertNotSame($toolSet, $scoped);
    }

    public function test_throws_when_prism_not_available(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $executor = Mockery::mock(ToolExecutor::class);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: null,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
        );

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('PrismPHP is not available');

        $toolSet->getTools(toolkitSlug: 'github');
    }

    public function test_throws_when_laravel_ai_not_available(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $executor = Mockery::mock(ToolExecutor::class);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: null,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
        );

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Laravel AI is not available');

        $toolSet->getLaravelAiTools(toolkitSlug: 'github');
    }

    public function test_get_tools_appends_custom_tools(): void
    {
        $composioTool = $this->createMockComposioTool('GITHUB_CREATE_ISSUE', 'Create an issue');

        $paginated = Mockery::mock(ToolsPaginated::class);
        $paginated->shouldReceive('getItems')->andReturn([$composioTool]);
        $paginated->shouldReceive('getNextCursor')->andReturn(null);

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getTools')->once()->andReturn($paginated);

        $executor = Mockery::mock(ToolExecutor::class);
        $schemaMapper = new SchemaMapper;
        $registry = (new CustomToolRegistry)
            ->register('LOCAL_GREET', 'Say hi', [], fn () => 'hello');

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: new PrismToolConverter($schemaMapper, $executor),
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
            customTools: $registry,
            schemaMapper: $schemaMapper,
        );

        $tools = $toolSet->getTools();
        $this->assertCount(2, $tools);
    }

    public function test_execute_runs_custom_tool_locally_without_calling_api(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldNotReceive('postToolsExecuteByToolSlug');

        $executor = new ToolExecutor($toolsApi, new HookManager);
        $registry = (new CustomToolRegistry)
            ->register('SUM', 'sum', [], fn (array $args) => ['total' => $args['a'] + $args['b']]);

        $toolSet = new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: null,
            laravelAiConverter: null,
            executor: $executor,
            hooks: new HookManager,
            customTools: $registry,
        );

        $result = $toolSet->execute('SUM', ['a' => 2, 'b' => 3]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['total' => 5], $result->data());
    }

    public function test_enums_returns_payload(): void
    {
        $expected = new stdClass;
        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getToolsEnum')->once()->andReturn($expected);

        $toolSet = $this->makeMinimalToolSet($toolsApi);

        $this->assertSame($expected, $toolSet->enums());
    }

    public function test_enums_throws_on_error(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getToolsEnum')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to fetch tool enums: boom');

        $this->makeMinimalToolSet($toolsApi)->enums();
    }

    public function test_generate_inputs_calls_endpoint(): void
    {
        $expected = new stdClass;
        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteByToolSlugInput')
            ->once()
            ->withArgs(function (string $slug, $req): bool {
                return $slug === 'GITHUB_CREATE_ISSUE' && $req->getText() === 'create issue about login bug';
            })
            ->andReturn($expected);

        $toolSet = $this->makeMinimalToolSet($toolsApi);

        $this->assertSame(
            $expected,
            $toolSet->generateInputs('GITHUB_CREATE_ISSUE', 'create issue about login bug'),
        );
    }

    public function test_proxy_execute_calls_endpoint(): void
    {
        $request = Mockery::mock(PostToolsExecuteProxyRequest::class);
        $expected = new stdClass;

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postToolsExecuteProxy')->once()->with($request)->andReturn($expected);

        $toolSet = $this->makeMinimalToolSet($toolsApi);

        $this->assertSame($expected, $toolSet->proxyExecute($request));
    }

    private function makeMinimalToolSet(ToolsApi $toolsApi): ComposioToolSet
    {
        return new ComposioToolSet(
            toolsApi: $toolsApi,
            prismConverter: null,
            laravelAiConverter: null,
            executor: Mockery::mock(ToolExecutor::class),
            hooks: new HookManager,
        );
    }

    private function createMockComposioTool(string $slug, string $description): ComposioTool
    {
        $tool = Mockery::mock(ComposioTool::class);
        $tool->shouldReceive('getSlug')->andReturn($slug);
        $tool->shouldReceive('getDescription')->andReturn($description);
        $tool->shouldReceive('getInputParameters')->andReturn([
            'properties' => [
                'owner' => ['type' => 'string', 'description' => 'Owner'],
            ],
            'required' => ['owner'],
        ]);

        return $tool;
    }
}
