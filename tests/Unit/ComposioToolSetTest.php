<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\Composio\Model\ToolsPaginated;
use BlockshiftNetwork\ComposioLaravel\ComposioToolSet;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool;

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
