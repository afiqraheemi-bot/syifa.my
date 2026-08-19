<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Domain;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicContactProfileException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicContactProfileTest extends TestCase
{
    public function test_empty_and_complete_profiles_are_valid_and_normalized(): void
    {
        $empty = new ClinicContactProfile;
        $complete = new ClinicContactProfile(
            ' +60 (3) 1234-5678 ',
            ' Care@EXAMPLE.COM ',
            " 12, Jalan Damai\r\nKuala Lumpur ",
            '+60 12-345 6789',
            3.139,
            101.6869,
        );

        self::assertNull($empty->operationalPhone);
        self::assertSame('+60312345678', $complete->operationalPhone);
        self::assertSame('Care@example.com', $complete->operationalEmail);
        self::assertSame("12, Jalan Damai\nKuala Lumpur", $complete->postalAddress);
        self::assertSame('+60123456789', $complete->whatsAppNumber);
    }

    public function test_phone_never_implies_whatsapp(): void
    {
        $profile = new ClinicContactProfile('+60312345678');

        self::assertNull($profile->whatsAppNumber);
    }

    public function test_common_malaysian_phone_formats_are_normalized_to_e164(): void
    {
        self::assertSame(
            '+60134079388',
            (new ClinicContactProfile(operationalPhone: '013-407 9388'))->operationalPhone,
        );
        self::assertSame(
            '+60134079388',
            (new ClinicContactProfile(whatsAppNumber: '60134079388'))->whatsAppNumber,
        );
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_profile_values_are_rejected(callable $factory): void
    {
        $this->expectException(InvalidClinicContactProfileException::class);
        $factory();
    }

    /** @return iterable<string, array{callable(): ClinicContactProfile}> */
    public static function invalidProfiles(): iterable
    {
        yield 'invalid email' => [static fn (): ClinicContactProfile => new ClinicContactProfile(operationalEmail: 'mailto:test@example.com')];
        yield 'html address' => [static fn (): ClinicContactProfile => new ClinicContactProfile(postalAddress: '<script>alert(1)</script>')];
        yield 'phone URL' => [static fn (): ClinicContactProfile => new ClinicContactProfile(operationalPhone: 'tel:+60312345678')];
        yield 'WhatsApp URL' => [static fn (): ClinicContactProfile => new ClinicContactProfile(whatsAppNumber: 'https://wa.me/60123456789')];
        yield 'latitude only' => [static fn (): ClinicContactProfile => new ClinicContactProfile(latitude: 1.0)];
        yield 'longitude only' => [static fn (): ClinicContactProfile => new ClinicContactProfile(longitude: 1.0)];
        yield 'latitude too low' => [static fn (): ClinicContactProfile => new ClinicContactProfile(latitude: -90.1, longitude: 0.0)];
        yield 'latitude too high' => [static fn (): ClinicContactProfile => new ClinicContactProfile(latitude: 90.1, longitude: 0.0)];
        yield 'longitude too low' => [static fn (): ClinicContactProfile => new ClinicContactProfile(latitude: 0.0, longitude: -180.1)];
        yield 'longitude too high' => [static fn (): ClinicContactProfile => new ClinicContactProfile(latitude: 0.0, longitude: 180.1)];
        yield 'blank not null' => [static fn (): ClinicContactProfile => new ClinicContactProfile(operationalPhone: ' ')];
    }

    public function test_coordinate_boundaries_are_accepted(): void
    {
        self::assertSame(-90.0, (new ClinicContactProfile(latitude: -90, longitude: -180))->latitude);
        self::assertSame(180.0, (new ClinicContactProfile(latitude: 90, longitude: 180))->longitude);
    }

    public function test_aggregate_update_is_idempotent_and_supports_explicit_clearing(): void
    {
        $clinic = Clinic::create(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([]),
            new DateTimeImmutable('2026-08-17T00:00:00Z'),
        );
        $profile = new ClinicContactProfile('+60312345678');

        self::assertTrue($clinic->updateContactProfile($profile, new DateTimeImmutable('2026-08-17T01:00:00Z')));
        self::assertFalse($clinic->updateContactProfile(new ClinicContactProfile('+60312345678'), new DateTimeImmutable('2026-08-17T02:00:00Z')));
        self::assertTrue($clinic->updateContactProfile(new ClinicContactProfile, new DateTimeImmutable('2026-08-17T03:00:00Z')));
        self::assertNull($clinic->contactProfile()->operationalPhone);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
