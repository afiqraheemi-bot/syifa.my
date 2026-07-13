<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\WorkforceCredentials;

use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PlatformWorkforceCredentialPersistenceMapperTest extends TestCase
{
    public function test_it_maps_storage_without_exposing_the_password_hash(): void
    {
        $row = new stdClass;
        $row->platform_identity_id = '00000000-0000-4000-8000-000000000001';
        $row->normalized_email = 'designer@example.test';
        $row->password_hash = '$2y$04$syntheticHashOnlyForMapperTest';
        $row->email_verification_status = 'verified';
        $row->email_verified_at = '2026-07-13 10:00:00+08:00';
        $row->account_status = 'active';
        $row->failed_attempt_count = 0;
        $row->lockout_until = null;
        $row->version = 1;
        $row->created_at = '2026-07-13 10:00:00+08:00';
        $row->updated_at = '2026-07-13 10:00:00+08:00';
        $mapper = new PlatformWorkforceCredentialPersistenceMapper;
        $record = $mapper->fromRow($row);
        $data = $mapper->toData($record);

        self::assertSame('designer@example.test', $data->normalizedEmail);
        self::assertTrue($data->emailVerified);
        self::assertFalse(property_exists($data, 'passwordHash'));
    }

    public function test_it_normalizes_email_consistently(): void
    {
        self::assertSame(
            'designer@example.test',
            (new PlatformWorkforceCredentialPersistenceMapper)->normalizeEmail('  DESIGNER@Example.Test  '),
        );
    }
}
