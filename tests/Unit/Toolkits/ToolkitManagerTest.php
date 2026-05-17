<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Toolkits;

use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostV31ToolkitsMultiRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Toolkits\ToolkitManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class ToolkitManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lists_toolkits(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31Toolkits')
            ->once()
            ->with('productivity', null, 'usage', false, null, 20, 'abc')
            ->andReturn($expected);

        $manager = new ToolkitManager($api);
        $result = $manager->list(category: 'productivity', includeDeprecated: false, sortBy: 'usage', limit: 20, cursor: 'abc');

        $this->assertSame($expected, $result);
    }

    public function test_throws_when_list_returns_error(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31Toolkits')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list toolkits: boom');

        (new ToolkitManager($api))->list();
    }

    public function test_gets_a_toolkit_by_slug(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31ToolkitsBySlug')
            ->once()
            ->with('github', 'latest')
            ->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->get('github'));
    }

    public function test_throws_when_get_returns_error(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('not found');

        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31ToolkitsBySlug')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage("Failed to get toolkit 'github': not found");

        (new ToolkitManager($api))->get('github');
    }

    public function test_lists_categories(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31ToolkitsCategories')->once()->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->categories());
    }

    public function test_returns_changelog(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getV31ToolkitsChangelog')->once()->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->changelog());
    }

    public function test_fetches_multiple_toolkits(): void
    {
        $request = Mockery::mock(PostV31ToolkitsMultiRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('postV31ToolkitsMulti')->once()->with($request)->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->fetchMultiple($request));
    }
}
