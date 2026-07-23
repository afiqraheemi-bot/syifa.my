<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingSuccessData;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

/**
 * The Success continuity mechanism ADR-031 decides in place of one-shot
 * session flash: a freshly-generated, high-entropy, opaque token — never
 * equal to or derived from `BookingReference` — bound to the originating
 * session and valid for a short, fixed window. Surviving a refresh is the
 * entire point; a token alone, without also controlling the session that
 * issued it, discloses nothing. Only one Success state is ever held per
 * session at a time (a visitor completes one booking at a time).
 */
final readonly class BookingSuccessTokenStore
{
    private const string SESSION_KEY = 'booking.success';

    private const int LIFETIME_MINUTES = 30;

    public function __construct(private Session $session) {}

    public function issue(BookingSuccessData $data, ?DateTimeImmutable $now = null): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = ($now ?? new DateTimeImmutable)->modify('+'.self::LIFETIME_MINUTES.' minutes');

        $this->session->put(self::SESSION_KEY, [
            'token' => $token,
            'reference' => $data->reference,
            'status' => $data->status,
            'submittedAt' => $data->submittedAt->format(DATE_ATOM),
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ]);

        return $token;
    }

    /**
     * Returns null for a missing, mismatched, or expired token — every case
     * indistinguishable from the token never having existed (ADR-031's
     * anti-enumeration requirement).
     */
    public function retrieve(string $token, ?DateTimeImmutable $now = null): ?BookingSuccessData
    {
        $stored = $this->session->get(self::SESSION_KEY);
        if (! is_array($stored) || ! hash_equals((string) ($stored['token'] ?? ''), $token)) {
            return null;
        }

        $expiresAt = is_string($stored['expiresAt'] ?? null) ? new DateTimeImmutable($stored['expiresAt']) : null;
        if ($expiresAt === null || ($now ?? new DateTimeImmutable) >= $expiresAt) {
            return null;
        }

        $reference = $stored['reference'] ?? null;
        $status = $stored['status'] ?? null;
        $submittedAt = $stored['submittedAt'] ?? null;
        if (! is_string($reference) || ! is_string($status) || ! is_string($submittedAt)) {
            return null;
        }

        return new BookingSuccessData($reference, $status, new DateTimeImmutable($submittedAt));
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
