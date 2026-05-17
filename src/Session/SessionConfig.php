<?php

declare(strict_types=1);

namespace BlockshiftNetwork\ComposioLaravel\Session;

use InvalidArgumentException;

class SessionConfig
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(string $userId): array
    {
        $config = $this->applyPreset($this->config);
        $payload = ['user_id' => $userId];

        $this->putIfPresent($payload, 'toolkits', $this->normalizeEnableDisable($config['toolkits'] ?? null));
        $this->putIfPresent($payload, 'tools', $this->normalizeTools($config['tools'] ?? null));
        $this->putIfPresent($payload, 'tags', $this->normalizeEnableDisable($config['tags'] ?? null));
        $this->putIfPresent($payload, 'auth_configs', $config['authConfigs'] ?? $config['auth_configs'] ?? null);
        $this->putIfPresent($payload, 'connected_accounts', $this->normalizeConnectedAccounts(
            $config['connectedAccounts'] ?? $config['connected_accounts'] ?? null,
        ));
        $this->putIfPresent($payload, 'manage_connections', $this->normalizeManageConnections(
            $config['manageConnections'] ?? $config['manage_connections'] ?? null,
        ));
        $this->putIfPresent($payload, 'workbench', $this->normalizeWorkbench($config['workbench'] ?? null));
        $this->putIfPresent($payload, 'search', $config['search'] ?? null);
        $this->putIfPresent($payload, 'execute', $config['execute'] ?? null);
        $this->putIfPresent($payload, 'multi_account', $config['multiAccount'] ?? $config['multi_account'] ?? null);
        $this->putIfPresent($payload, 'preload', $this->normalizePreload($config['preload'] ?? null));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function applyPreset(array $config): array
    {
        $preset = $config['sessionPreset'] ?? $config['session_preset'] ?? null;

        if ($preset === null) {
            return $config;
        }

        if ($preset !== 'direct_tools') {
            throw new InvalidArgumentException("Unsupported session preset '{$preset}'.");
        }

        $config['search'] ??= ['enable' => false];
        $config['execute'] ??= ['enable_multi_execute' => false];

        return $config;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putIfPresent(array &$payload, string $key, mixed $value): void
    {
        if ($value !== null && $value !== []) {
            $payload[$key] = $value;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeEnableDisable(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return ['enable' => [$value]];
        }

        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return ['enable' => $value];
        }

        $normalized = [];
        $enable = $value['enable'] ?? $value['enabled'] ?? $value['include'] ?? null;
        $disable = $value['disable'] ?? $value['disabled'] ?? $value['exclude'] ?? null;

        if ($enable !== null) {
            $normalized['enable'] = $this->arrayWrap($enable);
        }

        if ($disable !== null) {
            $normalized['disable'] = $this->arrayWrap($disable);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTools(mixed $tools): ?array
    {
        if ($tools === null) {
            return null;
        }

        if (! is_array($tools)) {
            return null;
        }

        if (array_is_list($tools)) {
            return ['enable' => $tools];
        }

        $normalized = [];

        foreach ($tools as $key => $value) {
            if (in_array($key, ['enable', 'enabled', 'disable', 'disabled', 'include', 'exclude'], true)) {
                $root = $this->normalizeEnableDisable($tools);

                return $root === [] ? null : $root;
            }

            $toolkitTools = $this->normalizeEnableDisable($value);
            if ($toolkitTools !== null && $toolkitTools !== []) {
                $normalized[$key] = $toolkitTools;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    private function normalizeConnectedAccounts(mixed $accounts): ?array
    {
        if ($accounts === null) {
            return null;
        }

        if (is_string($accounts)) {
            return ['default' => [$accounts]];
        }

        if (! is_array($accounts)) {
            return null;
        }

        if (array_is_list($accounts)) {
            return ['default' => $accounts];
        }

        $normalized = [];

        foreach ($accounts as $toolkit => $ids) {
            $normalized[$toolkit] = $this->arrayWrap($ids);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeManageConnections(mixed $manageConnections): ?array
    {
        if ($manageConnections === null) {
            return null;
        }

        if (is_bool($manageConnections)) {
            return ['enable' => $manageConnections];
        }

        if (! is_array($manageConnections)) {
            return null;
        }

        $normalized = [];

        $keyMap = [
            'enable' => ['enable', 'enabled', 'autoManageConnections', 'auto_manage_connections'],
            'callback_url' => ['callbackUrl', 'callback_url'],
            'infer_scopes_from_tools' => ['inferScopesFromTools', 'infer_scopes_from_tools'],
            'wait_for_connections' => ['waitForConnections', 'wait_for_connections'],
        ];

        foreach ($keyMap as $payloadKey => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $manageConnections)) {
                    $normalized[$payloadKey] = $manageConnections[$alias];
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeWorkbench(mixed $workbench): ?array
    {
        if ($workbench === null) {
            return null;
        }

        if (is_bool($workbench)) {
            return ['enable' => $workbench];
        }

        if (! is_array($workbench)) {
            return null;
        }

        $normalized = [];

        $keyMap = [
            'enable' => ['enable', 'enabled'],
            'enable_proxy_execution' => ['enableProxyExecution', 'enable_proxy_execution', 'proxyExecutionEnabled', 'proxy_execution_enabled'],
            'timeout_seconds' => ['timeoutSeconds', 'timeout_seconds'],
        ];

        foreach ($keyMap as $payloadKey => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $workbench)) {
                    $normalized[$payloadKey] = $workbench[$alias];
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePreload(mixed $preload): ?array
    {
        if ($preload === null) {
            return null;
        }

        if (! is_array($preload)) {
            return null;
        }

        if (isset($preload['tools']) && is_string($preload['tools'])) {
            $preload['tools'] = [$preload['tools']];
        }

        return $preload;
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayWrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? array_values($value) : [$value];
    }
}
