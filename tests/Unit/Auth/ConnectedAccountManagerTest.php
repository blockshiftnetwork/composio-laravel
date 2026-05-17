<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Auth;

use BlockshiftNetwork\Composio\Api\ConnectedAccountsApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchV31ConnectedAccountsByNanoIdStatusRequest;
use BlockshiftNetwork\Composio\Model\PostV31ConnectedAccountsLinkRequest;
use BlockshiftNetwork\ComposioLaravel\Auth\ConnectedAccountManager;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class ConnectedAccountManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_link_creates_auth_link_session(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ConnectedAccountsApi::class);
        $api->shouldReceive('postV31ConnectedAccountsLink')
            ->once()
            ->withArgs(function (PostV31ConnectedAccountsLinkRequest $request): bool {
                return $request->getUserId() === 'user_123'
                    && $request->getAuthConfigId() === 'ac_1'
                    && $request->getCallbackUrl() === 'https://app.test/callback';
            })
            ->andReturn($expected);

        $this->assertSame(
            $expected,
            (new ConnectedAccountManager($api))->link('user_123', 'ac_1', 'https://app.test/callback'),
        );
    }

    public function test_update_status_sends_enabled_flag(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(ConnectedAccountsApi::class);
        $api->shouldReceive('patchV31ConnectedAccountsByNanoIdStatus')
            ->once()
            ->withArgs(function (string $id, PatchV31ConnectedAccountsByNanoIdStatusRequest $request): bool {
                return $id === 'ca_1' && $request->getEnabled() === false;
            })
            ->andReturn($expected);

        $this->assertSame($expected, (new ConnectedAccountManager($api))->disable('ca_1'));
    }

    public function test_wait_for_connection_returns_active_account(): void
    {
        $account = new class
        {
            public function getStatus(): string
            {
                return 'ACTIVE';
            }
        };

        $api = Mockery::mock(ConnectedAccountsApi::class);
        $api->shouldReceive('getV31ConnectedAccountsByNanoid')
            ->once()
            ->with('ca_1')
            ->andReturn($account);

        $this->assertSame($account, (new ConnectedAccountManager($api))->waitForConnection('ca_1', 0, 1));
    }

    public function test_link_throws_on_error_response(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('bad request');

        $api = Mockery::mock(ConnectedAccountsApi::class);
        $api->shouldReceive('postV31ConnectedAccountsLink')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to create connected account link: bad request');

        (new ConnectedAccountManager($api))->link('user_123', 'ac_1');
    }
}
