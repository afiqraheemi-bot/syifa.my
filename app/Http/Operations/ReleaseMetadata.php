<?php

declare(strict_types=1);

namespace App\Http\Operations;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ReleaseMetadata
{
    public function __construct(
        private ConfigRepository $config,
        private CheckoutCommit $checkoutCommit,
    ) {}

    /**
     * @return array{version: string}
     */
    public function version(): array
    {
        return ['version' => $this->value('operations.release.version')];
    }

    /**
     * @return array{build_id: string, commit: string, built_at: string}
     */
    public function build(): array
    {
        return [
            'build_id' => $this->value('operations.release.build_id'),
            'commit' => $this->commit(),
            'built_at' => $this->value('operations.release.built_at'),
        ];
    }

    /**
     * @return array{service: string, component: string, api_version: string, version: string, build: array{build_id: string, commit: string, built_at: string}}
     */
    public function release(): array
    {
        return [
            'service' => $this->value('operations.application.service'),
            'component' => $this->value('operations.application.component'),
            'api_version' => $this->value('operations.application.api_version'),
            'version' => $this->value('operations.release.version'),
            'build' => $this->build(),
        ];
    }

    private function value(string $key): string
    {
        $value = $this->config->get($key, 'unknown');

        return is_string($value) && $value !== '' ? $value : 'unknown';
    }

    private function commit(): string
    {
        if ($this->config->get('operations.release.use_checkout_commit') === true) {
            $commit = $this->checkoutCommit->resolve();

            if ($commit !== null) {
                return $commit;
            }
        }

        return $this->value('operations.release.commit');
    }
}
