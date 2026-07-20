<?php

declare(strict_types=1);

namespace App\Support\Infrastructure;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class InfrastructureReadinessValidator
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function validate(): InfrastructureReadinessReport
    {
        if ($this->config->get('infrastructure_readiness.enabled') !== true) {
            return new InfrastructureReadinessReport([]);
        }

        $results = [];

        foreach ($this->capabilities() as $capability => $settings) {
            $results[] = $this->validateCapability($capability, $settings);
        }

        return new InfrastructureReadinessReport($results);
    }

    /**
     * @param  array{required: bool, default_config_key: string, configured_options_key?: string}  $settings
     */
    private function validateCapability(string $capability, array $settings): InfrastructureReadinessResult
    {
        $defaultValue = $this->config->get($settings['default_config_key']);

        if (! is_string($defaultValue) || $defaultValue === '') {
            return InfrastructureReadinessResult::notReady(
                $capability,
                $settings['required'],
                'missing_default',
            );
        }

        if (! isset($settings['configured_options_key'])) {
            return InfrastructureReadinessResult::ready($capability, $settings['required']);
        }

        $configuredOptions = $this->config->get($settings['configured_options_key']);

        if (! is_array($configuredOptions) || ! array_key_exists($defaultValue, $configuredOptions)) {
            return InfrastructureReadinessResult::notReady(
                $capability,
                $settings['required'],
                'missing_configuration',
            );
        }

        return InfrastructureReadinessResult::ready($capability, $settings['required']);
    }

    /**
     * @return array<string, array{required: bool, default_config_key: string, configured_options_key?: string}>
     */
    private function capabilities(): array
    {
        $configuredCapabilities = $this->config->get('infrastructure_readiness.capabilities', []);

        if (! is_array($configuredCapabilities)) {
            return [];
        }

        $capabilities = [];

        foreach ($configuredCapabilities as $capability => $settings) {
            if (! is_string($capability) || ! is_array($settings)) {
                continue;
            }

            $required = $settings['required'] ?? null;
            $defaultConfigKey = $settings['default_config_key'] ?? null;
            $configuredOptionsKey = $settings['configured_options_key'] ?? null;

            if (! is_bool($required) || ! is_string($defaultConfigKey)) {
                continue;
            }

            $capabilitySettings = [
                'required' => $required,
                'default_config_key' => $defaultConfigKey,
            ];

            if (is_string($configuredOptionsKey)) {
                $capabilitySettings['configured_options_key'] = $configuredOptionsKey;
            }

            $capabilities[$capability] = $capabilitySettings;
        }

        return $capabilities;
    }
}
