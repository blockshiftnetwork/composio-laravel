<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\Composio\Model\Tool as ComposioTool;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use Prism\Prism\Tool;
use Mockery;
use PHPUnit\Framework\TestCase;

class PrismToolConverterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_converts_composio_tool_to_prism_tool(): void
    {
        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $composioTool = $this->createComposioTool(
            slug: 'GITHUB_CREATE_ISSUE',
            description: 'Create a GitHub issue',
            inputParameters: [
                'properties' => [
                    'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                    'repo' => ['type' => 'string', 'description' => 'Repository name'],
                    'title' => ['type' => 'string', 'description' => 'Issue title'],
                ],
                'required' => ['owner', 'repo', 'title'],
            ],
        );

        $result = $converter->convert($composioTool, 'user_123', null, null);

        $this->assertInstanceOf(Tool::class, $result);
        $this->assertCount(3, $result->parameters());
        $this->assertContains('owner', $result->requiredParameters());
        $this->assertContains('repo', $result->requiredParameters());
        $this->assertContains('title', $result->requiredParameters());
    }

    public function test_converts_tool_with_empty_parameters(): void
    {
        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $composioTool = $this->createComposioTool(
            slug: 'GITHUB_LIST_REPOS',
            description: 'List repositories',
            inputParameters: [],
        );

        $result = $converter->convert($composioTool);

        $this->assertInstanceOf(Tool::class, $result);
        $this->assertCount(0, $result->parameters());
    }

    public function test_converts_tool_with_optional_parameters(): void
    {
        $executor = Mockery::mock(ToolExecutor::class);
        $converter = new PrismToolConverter(new SchemaMapper, $executor);

        $composioTool = $this->createComposioTool(
            slug: 'GITHUB_SEARCH',
            description: 'Search GitHub',
            inputParameters: [
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                    'page' => ['type' => 'integer', 'description' => 'Page number'],
                ],
                'required' => ['query'],
            ],
        );

        $result = $converter->convert($composioTool);

        $this->assertCount(2, $result->parameters());
        $this->assertContains('query', $result->requiredParameters());
        $this->assertNotContains('page', $result->requiredParameters());
    }

    private function createComposioTool(string $slug, string $description, array $inputParameters): ComposioTool
    {
        $tool = Mockery::mock(ComposioTool::class);
        $tool->shouldReceive('getSlug')->andReturn($slug);
        $tool->shouldReceive('getDescription')->andReturn($description);
        $tool->shouldReceive('getInputParameters')->andReturn($inputParameters);

        return $tool;
    }
}
