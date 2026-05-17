<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Tools;

use BlockshiftNetwork\Composio\Api\ToolsApi;
use BlockshiftNetwork\Composio\Model\PostV31ToolsExecuteByToolSlug200Response;
use BlockshiftNetwork\Composio\Model\Tool as ComposioToolModel;
use BlockshiftNetwork\Composio\Model\ToolsPaginated;
use BlockshiftNetwork\ComposioLaravel\Execution\ToolExecutor;
use BlockshiftNetwork\ComposioLaravel\Hooks\HookManager;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\PrismToolConverter;
use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use BlockshiftNetwork\ComposioLaravel\Tools\ToolManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool as PrismTool;

class ToolManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetches_and_converts_direct_tools_for_prism(): void
    {
        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('getV31Tools')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'github'
                && $args[1] === 'GITHUB_CREATE_ISSUE'
                && $args[4] === ['issues'])
            ->andReturn(new ToolsPaginated([
                'items' => [$this->makeTool('GITHUB_CREATE_ISSUE')],
                'next_cursor' => null,
            ]));

        $manager = $this->makeManager($toolsApi);

        $tools = $manager->get('github', ['GITHUB_CREATE_ISSUE'], ['issues']);

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(PrismTool::class, $tools[0]);
    }

    public function test_executes_direct_tool_with_user_and_connected_account(): void
    {
        $response = Mockery::mock(PostV31ToolsExecuteByToolSlug200Response::class);
        $response->shouldReceive('getSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn(['ok' => true]);
        $response->shouldReceive('getError')->andReturn(null);
        $response->shouldReceive('getLogId')->andReturn('log_123');

        $toolsApi = Mockery::mock(ToolsApi::class);
        $toolsApi->shouldReceive('postV31ToolsExecuteByToolSlug')
            ->once()
            ->withArgs(function (string $toolSlug, mixed $headers, mixed $request): bool {
                return $toolSlug === 'GITHUB_CREATE_ISSUE'
                    && $request->getArguments() === ['title' => 'Test']
                    && $request->getUserId() === 'user_123'
                    && $request->getConnectedAccountId() === 'ca_123';
            })
            ->andReturn($response);

        $manager = $this->makeManager($toolsApi);

        $result = $manager
            ->forUser('user_123')
            ->withConnectedAccount('ca_123')
            ->execute('GITHUB_CREATE_ISSUE', ['title' => 'Test']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['ok' => true], $result->data());
    }

    private function makeManager(ToolsApi $toolsApi): ToolManager
    {
        $hooks = new HookManager;
        $executor = new ToolExecutor($toolsApi, $hooks);

        return new ToolManager(
            toolsApi: $toolsApi,
            prismConverterFactory: fn ($executor) => new PrismToolConverter(new SchemaMapper, $executor),
            laravelAiConverterFactory: fn ($executor) => new LaravelAiToolConverter(new LaravelAiSchemaMapper, $executor),
            executor: $executor,
            hooks: $hooks,
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
