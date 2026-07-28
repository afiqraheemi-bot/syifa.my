<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\CustomDomain\CustomDomainRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomain;
use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomainStatus;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use stdClass;

final readonly class PostgresCustomDomainRepository implements CustomDomainRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function currentForWebsite(string $tenantId, string $websiteId): ?CustomDomain
    {
        $row = $this->connection->table('custom_domains')
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('status', '!=', CustomDomainStatus::Detached->value)
            ->first();

        return $row instanceof stdClass ? $this->domain($row) : null;
    }

    public function findOwned(string $tenantId, string $domainId): ?CustomDomain
    {
        $row = $this->connection->table('custom_domains')
            ->where('tenant_id', $tenantId)
            ->where('id', $domainId)
            ->first();

        return $row instanceof stdClass ? $this->domain($row) : null;
    }

    public function save(CustomDomain $domain): void
    {
        $payload = [
            'tenant_id' => $domain->tenantId,
            'website_id' => $domain->websiteId,
            'normalized_hostname' => $domain->hostname,
            'verification_token_hash' => $domain->verificationTokenHash,
            'status' => $domain->status()->value,
            'verified_at' => $domain->verifiedAt()?->format('Y-m-d H:i:s.uP'),
            'activated_at' => $domain->activatedAt()?->format('Y-m-d H:i:s.uP'),
            'detached_at' => $domain->detachedAt()?->format('Y-m-d H:i:s.uP'),
            'updated_at' => $domain->updatedAt()->format('Y-m-d H:i:s.uP'),
        ];

        try {
            if ($domain->version() === 0) {
                $this->connection->table('custom_domains')->insert([
                    'id' => $domain->id,
                    ...$payload,
                    'version' => 1,
                    'created_at' => $domain->createdAt->format('Y-m-d H:i:s.uP'),
                ]);
                $domain->synchronizeVersion(1);

                return;
            }
            $next = $domain->version() + 1;
            $affected = $this->connection->table('custom_domains')
                ->where('id', $domain->id)
                ->where('tenant_id', $domain->tenantId)
                ->where('version', $domain->version())
                ->update([...$payload, 'version' => $next]);
            if ($affected !== 1) {
                throw new InvalidWebsiteValueException('Custom Domain changed since it was loaded.');
            }
            $domain->synchronizeVersion($next);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new InvalidWebsiteValueException('Custom Domain hostname is already reserved.', previous: $exception);
            }
            throw $exception;
        }
    }

    private function domain(stdClass $row): CustomDomain
    {
        return new CustomDomain(
            (string) $row->id,
            (string) $row->tenant_id,
            (string) $row->website_id,
            (string) $row->normalized_hostname,
            (string) $row->verification_token_hash,
            CustomDomainStatus::from((string) $row->status),
            new DateTimeImmutable((string) $row->created_at),
            new DateTimeImmutable((string) $row->updated_at),
            $row->verified_at === null ? null : new DateTimeImmutable((string) $row->verified_at),
            $row->activated_at === null ? null : new DateTimeImmutable((string) $row->activated_at),
            $row->detached_at === null ? null : new DateTimeImmutable((string) $row->detached_at),
            (int) $row->version,
        );
    }
}
