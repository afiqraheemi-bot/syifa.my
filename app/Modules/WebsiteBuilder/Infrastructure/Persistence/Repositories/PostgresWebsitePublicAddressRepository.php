<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use RuntimeException;
use stdClass;

final readonly class PostgresWebsitePublicAddressRepository implements WebsitePublicAddressRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private ?string $localAliasBaseDomain = null,
        private ?int $localAliasPort = null,
        private string $canonicalBaseDomain = 'syifa.my',
    ) {}

    public function isAvailable(string $normalizedHost, string $websiteId): bool
    {
        $host = $this->normalize($normalizedHost);
        $owner = $this->connection->table('website_public_hosts')
            ->where('normalized_host', $host)
            ->value('website_id');

        if ($owner !== null && $owner !== $websiteId) {
            return false;
        }

        return ! $this->connection->table('custom_domains')
            ->where('normalized_hostname', $host)
            ->where('status', '!=', 'detached')
            ->exists();
    }

    public function reservePrimary(
        string $addressId,
        string $trustedTenantId,
        string $websiteId,
        string $normalizedHost,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData {
        $host = $this->normalize($normalizedHost);

        try {
            return $this->connection->transaction(function () use (
                $addressId,
                $trustedTenantId,
                $websiteId,
                $host,
                $at,
            ): WebsitePublicAddressData {
                $websiteOwned = $this->connection->table('websites')
                    ->where('id', $websiteId)
                    ->where('tenant_id', $trustedTenantId)
                    ->lockForUpdate()
                    ->exists();
                if (! $websiteOwned) {
                    throw new RuntimeException('Website was not found in the authorized scope.');
                }
                $existing = $this->connection->table('website_public_hosts')
                    ->where('normalized_host', $host)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof stdClass) {
                    if ((string) $existing->website_id !== $websiteId
                        || (string) $existing->tenant_id !== $trustedTenantId) {
                        throw new InvalidWebsiteValueException(
                            'The requested Website subdomain is not available.',
                        );
                    }
                    if ($existing->inactivated_at === null) {
                        return $this->forWebsite($trustedTenantId, $websiteId)
                            ?? throw new RuntimeException('Reserved Website address could not be read.');
                    }
                }

                $this->connection->table('website_public_hosts')
                    ->where('website_id', $websiteId)
                    ->where('tenant_id', $trustedTenantId)
                    ->where('is_primary', true)
                    ->whereNull('inactivated_at')
                    ->update([
                        'inactivated_at' => $this->timestamp($at),
                        'updated_at' => $this->timestamp($at),
                        'version' => $this->connection->raw('version + 1'),
                    ]);
                if ($existing instanceof stdClass) {
                    $this->connection->table('website_public_hosts')
                        ->where('id', (string) $existing->id)
                        ->update([
                            'is_primary' => true,
                            'activated_at' => null,
                            'inactivated_at' => null,
                            'updated_at' => $this->timestamp($at),
                            'version' => $this->connection->raw('version + 1'),
                        ]);
                } else {
                    $this->connection->table('website_public_hosts')->insert([
                        'id' => $addressId,
                        'website_id' => $websiteId,
                        'tenant_id' => $trustedTenantId,
                        'normalized_host' => $host,
                        'is_primary' => true,
                        'activated_at' => null,
                        'inactivated_at' => null,
                        'version' => 1,
                        'created_at' => $this->timestamp($at),
                        'updated_at' => $this->timestamp($at),
                    ]);
                }

                return $this->forWebsite($trustedTenantId, $websiteId)
                    ?? throw new RuntimeException('Reserved Website address could not be read.');
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23505', '23000'], true)) {
                throw new InvalidWebsiteValueException(
                    'The requested Website subdomain is not available.',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    public function activatePrimary(
        string $trustedTenantId,
        string $websiteId,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData {
        $affected = $this->connection->table('website_public_hosts')
            ->where('website_id', $websiteId)
            ->where('tenant_id', $trustedTenantId)
            ->where('is_primary', true)
            ->whereNull('activated_at')
            ->whereNull('inactivated_at')
            ->update([
                'activated_at' => $this->timestamp($at),
                'updated_at' => $this->timestamp($at),
                'version' => $this->connection->raw('version + 1'),
            ]);
        if ($affected !== 1) {
            $existing = $this->forWebsite($trustedTenantId, $websiteId);
            if ($existing === null || ! $existing->active) {
                throw new InvalidWebsiteValueException(
                    'Website publication requires a reserved primary public address.',
                );
            }
        }

        return $this->forWebsite($trustedTenantId, $websiteId)
            ?? throw new RuntimeException('Active Website address could not be read.');
    }

    public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData
    {
        $row = $this->baseQuery()
            ->where('website_public_hosts.tenant_id', $trustedTenantId)
            ->where('website_public_hosts.website_id', $websiteId)
            ->where('website_public_hosts.is_primary', true)
            ->whereNull('website_public_hosts.inactivated_at')
            ->first();

        return $row instanceof stdClass ? $this->data($row) : null;
    }

    public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
    {
        $row = $this->baseQuery()
            ->where('website_public_hosts.tenant_id', $trustedTenantId)
            ->where('website_public_hosts.is_primary', true)
            ->whereNull('website_public_hosts.inactivated_at')
            ->first();

        return $row instanceof stdClass ? $this->data($row) : null;
    }

    public function resolveActiveHost(string $host): ?WebsitePublicAddressData
    {
        $normalized = $this->tryNormalize($host);
        if ($normalized === null) {
            return null;
        }

        $row = $this->baseQuery()
            ->where('website_public_hosts.normalized_host', $normalized)
            ->where('website_public_hosts.is_primary', true)
            ->whereNotNull('website_public_hosts.activated_at')
            ->whereNull('website_public_hosts.inactivated_at')
            ->where('websites.lifecycle', 'published')
            ->first();

        if ($row instanceof stdClass) {
            return $this->data($row);
        }

        $custom = $this->connection->table('custom_domains')
            ->join('websites', function ($join): void {
                $join->on('websites.id', '=', 'custom_domains.website_id')
                    ->on('websites.tenant_id', '=', 'custom_domains.tenant_id');
            })
            ->where('custom_domains.normalized_hostname', $normalized)
            ->where('custom_domains.status', 'active')
            ->where('websites.lifecycle', 'published')
            ->first([
                'custom_domains.website_id',
                'custom_domains.tenant_id',
                'custom_domains.normalized_hostname',
            ]);

        return $custom instanceof stdClass
            ? new WebsitePublicAddressData(
                (string) $custom->website_id,
                (string) $custom->tenant_id,
                (string) $custom->normalized_hostname,
                'https://'.(string) $custom->normalized_hostname,
                true,
            )
            : null;
    }

    private function baseQuery(): Builder
    {
        return $this->connection->table('website_public_hosts')
            ->join('websites', function ($join): void {
                $join->on('websites.id', '=', 'website_public_hosts.website_id')
                    ->on('websites.tenant_id', '=', 'website_public_hosts.tenant_id');
            })
            ->select([
                'website_public_hosts.website_id',
                'website_public_hosts.tenant_id',
                'website_public_hosts.normalized_host',
                'website_public_hosts.activated_at',
                'website_public_hosts.inactivated_at',
                'websites.lifecycle',
            ]);
    }

    private function data(stdClass $row): WebsitePublicAddressData
    {
        $active = $row->activated_at !== null
            && $row->inactivated_at === null
            && (string) $row->lifecycle === 'published';
        $host = (string) $row->normalized_host;

        return new WebsitePublicAddressData(
            (string) $row->website_id,
            (string) $row->tenant_id,
            $host,
            $this->publicUrl($host),
            $active,
        );
    }

    private function publicUrl(string $host): string
    {
        $canonicalSuffix = '.'.strtolower($this->canonicalBaseDomain);
        if ($this->localAliasBaseDomain !== null && str_ends_with($host, $canonicalSuffix)) {
            $label = substr($host, 0, -strlen($canonicalSuffix));
            $port = $this->localAliasPort === null ? '' : ':'.$this->localAliasPort;

            return 'http://'.$label.'.'.$this->localAliasBaseDomain.$port;
        }

        return 'https://'.$host;
    }

    private function normalize(string $host): string
    {
        return $this->tryNormalize($host)
            ?? throw new InvalidWebsiteValueException('Public Website hostname is invalid.');
    }

    private function tryNormalize(string $host): ?string
    {
        $host = strtolower(rtrim(trim($host), '.'));

        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $host) === 1
            ? $host
            : null;
    }

    private function timestamp(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.uP');
    }
}
