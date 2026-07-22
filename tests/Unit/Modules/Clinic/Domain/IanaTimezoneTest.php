<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IanaTimezoneTest extends TestCase
{
    public function test_valid_iana_timezone_is_immutable_equal_and_deterministic(): void
    {
        $timezone = new IanaTimezone('Asia/Kuala_Lumpur');

        self::assertSame('Asia/Kuala_Lumpur', $timezone->value);
        self::assertSame('Asia/Kuala_Lumpur', (string) $timezone);
        self::assertTrue($timezone->equals(new IanaTimezone('Asia/Kuala_Lumpur')));
        self::assertFalse($timezone->equals(new IanaTimezone('Asia/Singapore')));
    }

    #[DataProvider('invalidTimezones')]
    public function test_invalid_empty_offset_or_ambiguous_timezone_is_rejected(string $timezone): void
    {
        $this->expectException(InvalidClinicOperationalTimeException::class);

        new IanaTimezone($timezone);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTimezones(): iterable
    {
        yield 'empty' => [''];
        yield 'offset' => ['+08:00'];
        yield 'abbreviation' => ['MYT'];
        yield 'ambiguous abbreviation' => ['CST'];
        yield 'unknown' => ['Asia/Not_A_Zone'];
    }
}
