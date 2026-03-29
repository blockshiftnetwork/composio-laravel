<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\LaravelAi\ComposioTool;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use Laravel\Ai\Contracts\Tool as LaravelAiToolContract;
use Mockery;
use PHPUnit\Framework\TestCase;

class LaravelAiToolConverterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(LaravelAiToolContract::class)) {
            $this->markTestSkipped('Laravel AI is not installed');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_converts_to_laravel_ai_tool(): void
    {
        $executor = Mockery::mock(ToolExecutor::class);
        $mapper = new LaravelAiSchemaMapper;
        $converter = new LaravelAiToolConverter($mapper, $executor);

        $composioTool = Mockery::mock(ComposioToolModel::class);
        $composioTool->shouldReceive('getSlug')->andReturn('GITHUB_CREATE_ISSUE');
        $composioTool->shouldReceive('getDescription')->andReturn('Create a GitHub issue');
        $composioTool->shouldReceive('getInputParameters')->andReturn([
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Issue title'],
            ],
            'required' => ['title'],
        ]);

        $result = $converter->convert($composioTool, 'user_123');

        $this->assertInstanceOf(ComposioTool::class, $result);
        $this->assertInstanceOf(LaravelAiToolContract::class, $result);
        $this->assertEquals('Create a GitHub issue', $result->description());
    }

    public function test_preserves_scoping_params(): void
    {
        $executor = Mockery::mock(ToolExecutor::class);
        $mapper = new LaravelAiSchemaMapper;
        $converter = new LaravelAiToolConverter($mapper, $executor);

        $composioTool = Mockery::mock(ComposioToolModel::class);
        $composioTool->shouldReceive('getSlug')->andReturn('GITHUB_CREATE_ISSUE');
        $composioTool->shouldReceive('getDescription')->andReturn('Create issue');
        $composioTool->shouldReceive('getInputParameters')->andReturn([]);

        $result = $converter->convert($composioTool, 'user_1', 'entity_1', 'conn_1');

        $this->assertInstanceOf(ComposioTool::class, $result);
    }
}
