<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http;

use Illuminate\Contracts\Session\Session;

/**
 * Replay/double-submission protection (ADR-027's Security Rule, ADR-029's
 * Security Review): a one-time token generated when Review renders and
 * invalidated the instant it is consumed — matched or not. A stale or reused
 * token is rejected as a Validation-category "please review and resubmit"
 * state, never processed twice.
 */
final readonly class BookingSubmissionTokenStore
{
    private const string SESSION_KEY = 'booking.submission_token';

    public function __construct(private Session $session) {}

    public function issue(): string
    {
        $token = bin2hex(random_bytes(24));
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }

    /** Always invalidates the stored token, whether or not it matches — a token is usable exactly once. */
    public function consume(string $submittedToken): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);
        $this->session->forget(self::SESSION_KEY);

        return is_string($stored) && $stored !== '' && hash_equals($stored, $submittedToken);
    }
}
