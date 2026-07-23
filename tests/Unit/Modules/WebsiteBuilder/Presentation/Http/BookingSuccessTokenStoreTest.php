<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Presentation\Http;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingSuccessData;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingSuccessTokenStore;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemorySession;

final class BookingSuccessTokenStoreTest extends TestCase
{
    public function test_a_token_issued_can_be_retrieved_with_the_same_data(): void
    {
        $store = new BookingSuccessTokenStore(new InMemorySession);
        $data = new BookingSuccessData('BOOK-STUB-ABC12345', 'submitted', new DateTimeImmutable('2026-08-01T09:00:00Z'));

        $token = $store->issue($data);
        $retrieved = $store->retrieve($token);

        self::assertNotNull($retrieved);
        self::assertSame('BOOK-STUB-ABC12345', $retrieved->reference);
        self::assertSame('submitted', $retrieved->status);
    }

    public function test_the_token_is_never_equal_to_or_derived_from_the_reference(): void
    {
        $store = new BookingSuccessTokenStore(new InMemorySession);
        $data = new BookingSuccessData('BOOK-STUB-ABC12345', 'submitted', new DateTimeImmutable);

        $token = $store->issue($data);

        self::assertNotSame($data->reference, $token);
        self::assertStringNotContainsString($data->reference, $token);
        self::assertGreaterThanOrEqual(32, strlen($token));
    }

    public function test_an_unknown_token_returns_null(): void
    {
        $store = new BookingSuccessTokenStore(new InMemorySession);

        self::assertNull($store->retrieve('does-not-exist'));
    }

    public function test_refreshing_within_the_lifetime_window_still_resolves(): void
    {
        $store = new BookingSuccessTokenStore(new InMemorySession);
        $issuedAt = new DateTimeImmutable('2026-08-01T09:00:00Z');
        $token = $store->issue(new BookingSuccessData('BOOK-STUB-ABC12345', 'submitted', $issuedAt), $issuedAt);

        $retrieved = $store->retrieve($token, $issuedAt->modify('+29 minutes'));

        self::assertNotNull($retrieved);
    }

    public function test_an_expired_token_returns_null_indistinguishably(): void
    {
        $store = new BookingSuccessTokenStore(new InMemorySession);
        $issuedAt = new DateTimeImmutable('2026-08-01T09:00:00Z');
        $token = $store->issue(new BookingSuccessData('BOOK-STUB-ABC12345', 'submitted', $issuedAt), $issuedAt);

        $retrieved = $store->retrieve($token, $issuedAt->modify('+31 minutes'));

        self::assertNull($retrieved);
    }

    public function test_clear_removes_the_stored_success_state(): void
    {
        $session = new InMemorySession;
        $store = new BookingSuccessTokenStore($session);
        $token = $store->issue(new BookingSuccessData('BOOK-STUB-ABC12345', 'submitted', new DateTimeImmutable));

        $store->clear();

        self::assertNull($store->retrieve($token));
    }
}
