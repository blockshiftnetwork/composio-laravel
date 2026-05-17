<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Triggers;

use BlockshiftNetwork\Composio\Api\TriggersApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\Composio\Model\PatchV31TriggerInstancesManageByTriggerIdRequest;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Triggers\TriggerManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class TriggerManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lists_trigger_types(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('getV31TriggersTypes')
            ->once()
            ->with(['github'], null, 50, 'cur')
            ->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->listTypes(['github'], null, 50, 'cur'));
    }

    public function test_throws_when_list_types_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('getV31TriggersTypes')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list trigger types: boom');

        (new TriggerManager($api))->listTypes();
    }

    public function test_gets_trigger_type(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('getV31TriggersTypesBySlug')->once()->with('GITHUB_PUSH', null)->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->getType('GITHUB_PUSH'));
    }

    public function test_returns_types_enum(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('getV31TriggersTypesListEnum')->once()->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->listTypesEnum());
    }

    public function test_lists_trigger_instances(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('getV31TriggerInstancesActive')
            ->once()
            ->with(null, ['acct'], null, null, null, null, null, null, true, null, true, null, null, 25, null)
            ->andReturn($expected);

        $this->assertSame(
            $expected,
            (new TriggerManager($api))->listInstances(
                connectedAccountIds: ['acct'],
                showDisabled: true,
                limit: 25,
            ),
        );
    }

    public function test_upserts_a_trigger_instance(): void
    {
        $request = new stdClass;
        $expected = new stdClass;

        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('postV31TriggerInstancesBySlugUpsert')
            ->once()
            ->with('GITHUB_PUSH', $request)
            ->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->upsert('GITHUB_PUSH', $request));
    }

    public function test_enable_sends_enable_status(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('patchV31TriggerInstancesManageByTriggerId')
            ->once()
            ->withArgs(function (string $id, PatchV31TriggerInstancesManageByTriggerIdRequest $req): bool {
                return $id === 'trig_1' && $req->getStatus() === 'enable';
            })
            ->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->enable('trig_1'));
    }

    public function test_disable_sends_disable_status(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('patchV31TriggerInstancesManageByTriggerId')
            ->once()
            ->withArgs(function (string $id, PatchV31TriggerInstancesManageByTriggerIdRequest $req): bool {
                return $id === 'trig_2' && $req->getStatus() === 'disable';
            })
            ->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->disable('trig_2'));
    }

    public function test_throws_when_set_status_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('forbidden');

        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('patchV31TriggerInstancesManageByTriggerId')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage("Failed to enable trigger instance 'trig_3': forbidden");

        (new TriggerManager($api))->enable('trig_3');
    }

    public function test_deletes_trigger_instance(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(TriggersApi::class);
        $api->shouldReceive('deleteV31TriggerInstancesManageByTriggerId')
            ->once()
            ->with('trig_4')
            ->andReturn($expected);

        $this->assertSame($expected, (new TriggerManager($api))->delete('trig_4'));
    }
}
