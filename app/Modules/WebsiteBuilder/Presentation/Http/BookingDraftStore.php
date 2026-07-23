<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use Illuminate\Contracts\Session\Session;

/**
 * Session-backed only — never persisted anywhere beyond the visitor's own
 * session (Sprint 1 / ADR-029's Form Flow). `BookingDraft` itself is immutable;
 * this store's job is solely to hold the current snapshot and replace it
 * wholesale on `save()`, matching the "never lose already-entered data" rule
 * across Back/Edit navigation.
 */
final readonly class BookingDraftStore
{
    private const string SESSION_KEY = 'booking.draft';

    public function __construct(private Session $session) {}

    public function current(): BookingDraft
    {
        $data = $this->session->get(self::SESSION_KEY);

        return is_array($data) ? BookingDraft::fromArray($data) : new BookingDraft;
    }

    public function save(BookingDraft $draft): void
    {
        $this->session->put(self::SESSION_KEY, $draft->toArray());
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
