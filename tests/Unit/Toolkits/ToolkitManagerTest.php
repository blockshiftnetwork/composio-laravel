<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Toolkits;

use BlockshiftNetwork\Composio\Api\ToolkitsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PostToolkitsMultiRequest;
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
        $api->shouldReceive('getToolkits')
            ->once()
            ->with('productivity', null, false, 'usage', 20, 'abc')
            ->andReturn($expected);

        $manager = new ToolkitManager($api);
        $result = $manager->list(category: 'productivity', isLocal: false, sortBy: 'usage', limit: 20, cursor: 'abc');

        $this->assertSame($expected, $result);
    }

    public function test_throws_when_list_returns_error(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getToolkits')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list toolkits: boom');

        (new ToolkitManager($api))->list();
    }

    public function test_gets_a_toolkit_by_slug(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getToolkitsBySlug')
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
        $api->shouldReceive('getToolkitsBySlug')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage("Failed to get toolkit 'github': not found");

        (new ToolkitManager($api))->get('github');
    }

    public function test_lists_categories(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getToolkitsCategories')->once()->with('1')->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->categories(cache: '1'));
    }

    public function test_returns_changelog(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('getToolkitsChangelog')->once()->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->changelog());
    }

    public function test_fetches_multiple_toolkits(): void
    {
        $request = Mockery::mock(PostToolkitsMultiRequest::class);
        $expected = new stdClass;

        $api = Mockery::mock(ToolkitsApi::class);
        $api->shouldReceive('postToolkitsMulti')->once()->with($request)->andReturn($expected);

        $this->assertSame($expected, (new ToolkitManager($api))->fetchMultiple($request));
    }
}
