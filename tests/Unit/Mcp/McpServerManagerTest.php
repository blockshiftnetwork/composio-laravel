<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Mcp;

use BlockshiftNetwork\Composio\Api\MCPApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchV31McpByIdRequest;
use BlockshiftNetwork\Composio\Model\PostV31McpServersCustomRequest;
use BlockshiftNetwork\Composio\Model\PostV31McpServersGenerateRequest;
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
        $api->shouldReceive('getV31McpServers')
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
        $api->shouldReceive('getV31McpServers')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list MCP servers: boom');

        (new McpServerManager($api))->list();
    }

    public function test_gets_a_server(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getV31McpById')->once()->with('srv_1')->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->get('srv_1'));
    }

    public function test_creates_custom_server(): void
    {
        $request = Mockery::mock(PostV31McpServersCustomRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postV31McpServersCustom')->once()->with($request)->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->createCustomServer($request));
    }

    public function test_generates_server_url(): void
    {
        $request = Mockery::mock(PostV31McpServersGenerateRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postV31McpServersGenerate')->once()->with($request)->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->generate($request));
    }

    public function test_throws_when_create_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('bad request');

        $request = Mockery::mock(PostV31McpServersCustomRequest::class);
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('postV31McpServersCustom')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to create custom MCP server: bad request');

        (new McpServerManager($api))->createCustomServer($request);
    }

    public function test_updates_a_server(): void
    {
        $request = Mockery::mock(PatchV31McpByIdRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('patchV31McpById')->once()->with('srv_1', $request)->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->update('srv_1', $request));
    }

    public function test_deletes_a_server(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('deleteV31McpById')->once()->with('srv_1')->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->delete('srv_1'));
    }

    public function test_lists_instances(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('getV31McpServersByServerIdInstances')
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
        $api->shouldReceive('postV31McpServersByServerIdInstances')
            ->once()
            ->with('srv_1', $request)
            ->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->createInstance('srv_1', $request));
    }

    public function test_deletes_instance(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(MCPApi::class);
        $api->shouldReceive('deleteV31McpServersByServerIdInstancesByInstanceId')
            ->once()
            ->with('srv_1', 'inst_1')
            ->andReturn($expected);

        $this->assertSame($expected, (new McpServerManager($api))->deleteInstance('srv_1', 'inst_1'));
    }
}
