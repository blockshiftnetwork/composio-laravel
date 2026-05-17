<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Auth;

use BlockshiftNetwork\Composio\Api\AuthConfigsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\ComposioLaravel\Auth\AuthConfigManager;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class AuthConfigManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_enable_updates_status_to_enabled(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(AuthConfigsApi::class);
        $api->shouldReceive('patchV31AuthConfigsByNanoidByStatus')
            ->once()
            ->with('ac_1', 'ENABLED')
            ->andReturn($expected);

        $this->assertSame($expected, (new AuthConfigManager($api))->enable('ac_1'));
    }

    public function test_disable_updates_status_to_disabled(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(AuthConfigsApi::class);
        $api->shouldReceive('patchV31AuthConfigsByNanoidByStatus')
            ->once()
            ->with('ac_1', 'DISABLED')
            ->andReturn($expected);

        $this->assertSame($expected, (new AuthConfigManager($api))->disable('ac_1'));
    }

    public function test_update_status_throws_on_error_response(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('forbidden');

        $api = Mockery::mock(AuthConfigsApi::class);
        $api->shouldReceive('patchV31AuthConfigsByNanoidByStatus')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage("Failed to update auth config 'ac_1' status: forbidden");

        (new AuthConfigManager($api))->enable('ac_1');
    }
}
