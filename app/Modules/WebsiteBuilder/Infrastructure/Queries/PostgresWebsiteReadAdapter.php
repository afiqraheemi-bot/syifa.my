<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteDetailData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSummaryData;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidWebsiteStorageStateException;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresWebsiteReadAdapter implements WebsiteReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $trustedTenantId): ?WebsiteSummaryData
    {
        $row = $this->row($trustedTenantId);

        return $row === null ? null : new WebsiteSummaryData($this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'clinic_name'), $this->string($row, 'template_id'), $this->string($row, 'lifecycle'));
    }

    public function detail(string $trustedTenantId): ?WebsiteDetailData
    {
        $row = $this->row($trustedTenantId);
        if ($row === null) {
            return null;
        }
        $links = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
            $value = $row->{$channel.'_url'} ?? null;
            if (is_string($value) && $value !== '') {
                $links[$channel] = $value;
            }
        }

        return new WebsiteDetailData($this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'template_id'), $this->string($row, 'lifecycle'), $this->string($row, 'clinic_name'), $this->nullableString($row, 'tagline'), $this->string($row, 'primary_color'), $this->string($row, 'secondary_color'), $this->nullableString($row, 'logo_reference'), $this->nullableString($row, 'favicon_reference'), $this->string($row, 'contact_email'), $this->string($row, 'contact_phone'), $this->string($row, 'address'), $links);
    }

    private function row(string $tenantId): ?stdClass
    {
        return $this->connection->table('websites')->where('tenant_id', $tenantId)->first();
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidWebsiteStorageStateException('Stored Website read data is invalid.');
        }

return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        } throw new InvalidWebsiteStorageStateException('Stored Website read data is invalid.');
    }
}
