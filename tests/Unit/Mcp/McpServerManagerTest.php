<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Mcp;

use BlockshiftNetwork\Composio\Api\MCPApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchMcpByIdRequest;
use BlockshiftNetwork\Composio\Model\PostMcpServersCustomRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Mcp\McpServerManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class McpServerManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lists_servers(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getMcpServers')
            ->once()
            ->with('mine', 'github,linear', null, 'updated_at', 'desc', 1, 10)
            ->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->list(name: 'mine', toolkits: ['github', 'linear']));
    }

    public function test_throws_when_list_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getMcpServers')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list MCP servers: boom');

        (new McpServerManager($api))->list();
    }

    public function test_gets_a_server(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getMcpById')->once()->with('srv_1')->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->get('srv_1'));
    }

    public function test_creates_custom_server(): void
    {
        $request = Mockery::mock(PostMcpServersCustomRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postMcpServersCustom')->once()->with($request)->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->createCustomServer($request));
    }

    public function test_throws_when_create_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('bad request');

        $request = Mockery::mock(PostMcpServersCustomRequest::class);
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postMcpServersCustom')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to create custom MCP server: bad request');

        (new McpServerManager($api))->createCustomServer($request);
    }

    public function test_updates_a_server(): void
    {
        $request = Mockery::mock(PatchMcpByIdRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('patchMcpById')->once()->with('srv_1', $request)->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->update('srv_1', $request));
    }

    public function test_deletes_a_server(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('deleteMcpById')->once()->with('srv_1')->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->delete('srv_1'));
    }

    public function test_lists_instances(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getMcpServersByServerIdInstances')
            ->once()
            ->with('srv_1', 2, 50, 'foo', 'updated_at', 'desc')
            ->andReturn($expected);

        $this->assertSame(
            $expected,
            (new McpServerManager($api))->listInstances('srv_1', 2, 50, 'foo'),
        );
    }

    public function test_creates_instance(): void
    {
        $request = new stdClass;
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postMcpServersByServerIdInstances')
            ->once()
            ->with('srv_1', $request)
            ->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->createInstance('srv_1', $request));
    }

    public function test_deletes_instance(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('deleteMcpServersByServerIdInstancesByInstanceId')
            ->once()
            ->with('srv_1', 'inst_1')
            ->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->deleteInstance('srv_1', 'inst_1'));
    }
}
