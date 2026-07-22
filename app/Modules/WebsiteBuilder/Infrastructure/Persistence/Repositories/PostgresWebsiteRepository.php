<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidWebsiteStorageStateException;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresWebsiteRepository implements WebsiteRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private WebsitePersistenceMapper $mapper) {}

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        $row = $this->connection->table('websites')->where('tenant_id', $tenantId->value)->where('id', $websiteId->value)->first();

        return $row === null ? null : $this->domain($row);
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        $row = $this->connection->table('websites')->where('tenant_id', $tenantId->value)->first();

        return $row === null ? null : $this->domain($row);
    }

    public function save(Website $website): void
    {
        $record = $this->mapper->record($website);
        $version = $website->version() === 0 ? $this->insert($record) : $this->update($record);
        $website->synchronizeVersion($version);
    }

    private function insert(WebsiteStorageRecord $record): int
    {
        $now = $this->timestamp(new DateTimeImmutable);
        $this->connection->table('websites')->insert([...$this->payload($record, 1), 'created_at' => $now, 'updated_at' => $now]);

        return 1;
    }

    private function update(WebsiteStorageRecord $record): int
    {
        $next = $record->version + 1;
        $affected = $this->connection->table('websites')->where('id', $record->id)->where('tenant_id', $record->tenantId)->where('version', $record->version)->update([...$this->payload($record, $next), 'updated_at' => $this->timestamp(new DateTimeImmutable)]);
        if ($affected !== 1) {
            throw new StaleWebsiteWriteException('Website write rejected because its version is stale.');
        }

        return $next;
    }

    /** @return array<string, mixed> */
    private function payload(WebsiteStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id, 'tenant_id' => $record->tenantId, 'template_id' => $record->templateId, 'lifecycle' => $record->lifecycle,
            'clinic_name' => $record->clinicName, 'tagline' => $record->tagline, 'primary_color' => $record->primaryColor, 'secondary_color' => $record->secondaryColor,
            'logo_reference' => $record->logoReference, 'favicon_reference' => $record->faviconReference, 'contact_email' => $record->contactEmail,
            'contact_phone' => $record->contactPhone, 'address' => $record->address,
            'facebook_url' => $record->socialLinks['facebook'] ?? null, 'instagram_url' => $record->socialLinks['instagram'] ?? null,
            'youtube_url' => $record->socialLinks['youtube'] ?? null, 'tiktok_url' => $record->socialLinks['tiktok'] ?? null, 'linkedin_url' => $record->socialLinks['linkedin'] ?? null,
            'domain_created_at' => $this->timestamp($record->domainCreatedAt), 'domain_updated_at' => $this->timestamp($record->domainUpdatedAt), 'version' => $version,
        ];
    }

    private function domain(stdClass $row): Website
    {
        $links = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
            $value = $row->{$channel.'_url'} ?? null;
            if ($value !== null) {
                if (! is_string($value)) {
                    throw new InvalidWebsiteStorageStateException('Stored Website social link is invalid.');
                }
                $links[$channel] = $value;
            }
        }
        try {
            return $this->mapper->toDomain(new WebsiteStorageRecord(
                $this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'template_id'), $this->string($row, 'lifecycle'),
                $this->string($row, 'clinic_name'), $this->nullableString($row, 'tagline'), $this->string($row, 'primary_color'), $this->string($row, 'secondary_color'),
                $this->nullableString($row, 'logo_reference'), $this->nullableString($row, 'favicon_reference'), $this->string($row, 'contact_email'),
                $this->string($row, 'contact_phone'), $this->string($row, 'address'), $links, $this->dateTime($row->domain_created_at ?? null),
                $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version'),
            ));
        } catch (InvalidWebsiteValueException $exception) {
            throw new InvalidWebsiteStorageStateException('Stored Website failed Domain validation.', 0, $exception);
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }
        throw new InvalidWebsiteStorageStateException('Website timestamp is invalid.');
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
