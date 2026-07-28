<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomain;
use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomainStatus;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CustomDomainTest extends TestCase
{
    public function test_it_normalizes_and_enforces_the_verification_lifecycle(): void
    {
        $domain = CustomDomain::request(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            ' Clinic.Example.COM. ',
            str_repeat('a', 64),
            new DateTimeImmutable('2026-09-05T00:00:00Z'),
        );

        self::assertSame('clinic.example.com', $domain->hostname);
        self::assertSame(CustomDomainStatus::VerificationPending, $domain->status());
        $domain->markVerified(new DateTimeImmutable('2026-09-05T00:01:00Z'));
        $domain->activate(new DateTimeImmutable('2026-09-05T00:02:00Z'));
        $domain->detach(new DateTimeImmutable('2026-09-05T00:03:00Z'));
        self::assertSame(CustomDomainStatus::Detached, $domain->status());
    }

    public function test_it_rejects_syifa_default_hosts_as_custom_domains(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        CustomDomain::normalizeHostname('clinic.syifa.my');
    }

    public function test_it_cannot_activate_before_verification(): void
    {
        $domain = CustomDomain::request(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            'clinic.example.com',
            str_repeat('a', 64),
            new DateTimeImmutable('2026-09-05T00:00:00Z'),
        );
        $this->expectException(InvalidWebsiteValueException::class);
        $domain->activate(new DateTimeImmutable('2026-09-05T00:01:00Z'));
    }
}
