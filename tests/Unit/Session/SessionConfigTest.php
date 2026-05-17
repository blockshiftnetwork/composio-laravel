<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Session;

use BlockshiftNetwork\ComposioLaravel\Session\SessionConfig;
use PHPUnit\Framework\TestCase;

class SessionConfigTest extends TestCase
{
    public function test_builds_typescript_style_session_payload(): void
    {
        $payload = SessionConfig::fromArray([
            'toolkits' => ['github', 'slack'],
            'tools' => [
                'github' => ['GITHUB_CREATE_ISSUE'],
                'slack' => ['disable' => ['SLACK_DELETE_MESSAGE']],
            ],
            'tags' => ['readOnlyHint'],
            'authConfigs' => ['github' => 'ac_github'],
            'connectedAccounts' => [
                'github' => 'ca_github',
                'slack' => ['ca_slack_1', 'ca_slack_2'],
            ],
            'manageConnections' => [
                'enable' => true,
                'callbackUrl' => 'https://app.test/composio/callback',
                'inferScopesFromTools' => true,
            ],
            'workbench' => [
                'enableProxyExecution' => false,
                'timeoutSeconds' => 30,
            ],
        ])->toPayload('user_123');

        $this->assertSame('user_123', $payload['user_id']);
        $this->assertSame(['enable' => ['github', 'slack']], $payload['toolkits']);
        $this->assertSame(['enable' => ['GITHUB_CREATE_ISSUE']], $payload['tools']['github']);
        $this->assertSame(['disable' => ['SLACK_DELETE_MESSAGE']], $payload['tools']['slack']);
        $this->assertSame(['enable' => ['readOnlyHint']], $payload['tags']);
        $this->assertSame(['github' => 'ac_github'], $payload['auth_configs']);
        $this->assertSame(['github' => ['ca_github'], 'slack' => ['ca_slack_1', 'ca_slack_2']], $payload['connected_accounts']);
        $this->assertSame([
            'enable' => true,
            'callback_url' => 'https://app.test/composio/callback',
            'infer_scopes_from_tools' => true,
        ], $payload['manage_connections']);
        $this->assertSame([
            'enable_proxy_execution' => false,
            'timeout_seconds' => 30,
        ], $payload['workbench']);
    }

    public function test_direct_tools_preset_disables_connection_management_and_preloads_tools(): void
    {
        $payload = SessionConfig::fromArray([
            'sessionPreset' => 'direct_tools',
            'toolkits' => ['github'],
        ])->toPayload('user_123');

        $this->assertSame(['enable' => ['github']], $payload['toolkits']);
        $this->assertSame(['enable' => false], $payload['search']);
        $this->assertSame(['enable_multi_execute' => false], $payload['execute']);
        $this->assertArrayNotHasKey('manage_connections', $payload);
        $this->assertArrayNotHasKey('preload', $payload);
        $this->assertArrayNotHasKey('workbench', $payload);
    }
}
