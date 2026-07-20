<?php

declare(strict_types=1);

namespace App\Support\Production;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;

final readonly class ProductionEnvironmentGuard
{
    public function __construct(
        private ApplicationContract $application,
        private ConfigRepository $config,
    ) {}

    public function validate(): void
    {
        if ($this->config->get('production_guard.enabled') !== true) {
            return;
        }

        $environment = $this->config->get('app.env');

        if (! is_string($environment) || ! in_array($environment, $this->protectedEnvironments(), true)) {
            return;
        }

        if ($this->application->runningInConsole() && $this->config->get('production_guard.validate_console') !== true) {
            return;
        }

        $violations = [
            ...$this->missingRequiredConfigViolations(),
            ...$this->unexpectedValueViolations(),
            ...$this->invalidUrlSchemeViolations(),
        ];

        if ($violations !== []) {
            throw new ProductionEnvironmentGuardException($violations);
        }
    }

    /**
     * @return list<string>
     */
    private function missingRequiredConfigViolations(): array
    {
        $violations = [];

        foreach ($this->requiredConfigKeys() as $key) {
            $value = $this->config->get($key);

            if ($value === null || $value === '') {
                $violations[] = $key.'.required';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function unexpectedValueViolations(): array
    {
        $violations = [];

        foreach ($this->expectedValues() as $key => $expectedValue) {
            if ($this->config->get($key) !== $expectedValue) {
                $violations[] = $key.'.unsafe';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function invalidUrlSchemeViolations(): array
    {
        $violations = [];

        foreach ($this->requiredUrlSchemes() as $key => $requiredScheme) {
            $value = $this->config->get($key);

            if (! is_string($value) || parse_url($value, PHP_URL_SCHEME) !== $requiredScheme) {
                $violations[] = $key.'.scheme';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function protectedEnvironments(): array
    {
        $environments = $this->config->get('production_guard.protected_environments', []);

        if (! is_array($environments)) {
            return [];
        }

        return array_values(array_filter($environments, is_string(...)));
    }

    /**
     * @return list<string>
     */
    private function requiredConfigKeys(): array
    {
        $keys = $this->config->get('production_guard.required_config', []);

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, is_string(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedValues(): array
    {
        $expectedValues = $this->config->get('production_guard.expected_values', []);

        if (! is_array($expectedValues)) {
            return [];
        }

        $normalized = [];

        foreach ($expectedValues as $key => $expectedValue) {
            if (is_string($key)) {
                $normalized[$key] = $expectedValue;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function requiredUrlSchemes(): array
    {
        $schemes = $this->config->get('production_guard.required_url_schemes', []);

        if (! is_array($schemes)) {
            return [];
        }

        $normalized = [];

        foreach ($schemes as $key => $scheme) {
            if (is_string($key) && is_string($scheme)) {
                $normalized[$key] = $scheme;
            }
        }

        return $normalized;
    }
}
