<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Presentation\Http;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingDraftStore;
use Illuminate\Contracts\Session\Session;
use PHPUnit\Framework\TestCase;

final class BookingDraftStoreTest extends TestCase
{
    public function test_current_returns_an_empty_draft_when_nothing_is_stored(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->with('booking.draft')->willReturn(null);

        $draft = (new BookingDraftStore($session))->current();

        self::assertTrue($draft->isEmpty());
    }

    public function test_save_writes_the_drafts_array_shape_to_the_session(): void
    {
        $draft = (new BookingDraft)->withService('service-1');

        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('put')->with('booking.draft', $draft->toArray());

        (new BookingDraftStore($session))->save($draft);
    }

    public function test_current_reconstructs_the_draft_from_stored_data(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->with('booking.draft')->willReturn(['serviceId' => 'service-1', 'consent' => true]);

        $draft = (new BookingDraftStore($session))->current();

        self::assertSame('service-1', $draft->serviceId);
        self::assertTrue($draft->consent);
    }

    public function test_clear_forgets_the_session_key(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects(self::once())->method('forget')->with('booking.draft');

        (new BookingDraftStore($session))->clear();
    }
}
