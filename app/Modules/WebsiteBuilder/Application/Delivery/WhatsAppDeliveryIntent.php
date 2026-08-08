<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

/**
 * The governed, closed vocabulary of approved WhatsApp Delivery Intents
 * (ADR-026). The Intent is the contract: a small, finite, platform-owned
 * set of reasons a visitor may open a WhatsApp conversation. It carries no
 * language and no text of its own.
 *
 * The localized message a given Intent produces is a Delivery implementation
 * detail, not part of the contract — `localizedMessage()` may later select
 * text by locale without changing this enum's cases, without any Domain
 * change, and without any tenant-visible behavior change. Message text is
 * authored entirely in Delivery: never tenant-configurable, never stored,
 * never read from Domain or request input, and never composed from a
 * template or placeholder.
 *
 * Only `GeneralEnquiry` is currently wired to a public touchpoint (the
 * Contact section's WhatsApp action); `Service`, `Doctor`, and `Booking`
 * are governed and reserved for a future contextual touchpoint (e.g. a
 * per-Service or per-Doctor enquiry action, or the eventual Public Booking
 * Contract's own "enquire before booking" path) without requiring a new
 * Delivery Intent decision when that touchpoint is built.
 */
enum WhatsAppDeliveryIntent
{
    case GeneralEnquiry;
    case Service;
    case Doctor;
    case Booking;

    /**
     * Resolves this Intent to its current localized message. Only Delivery
     * calls this; the result is never persisted, never tenant-editable, and
     * carries no placeholder or interpolation of any kind. Today this
     * always resolves to English text — a future locale-aware
     * implementation may vary the returned string per supported language
     * (Malay, English, Arabic, or others) without changing this method's
     * signature, this enum's cases, or anything outside Delivery.
     */
    public function localizedMessage(): string
    {
        return match ($this) {
            self::GeneralEnquiry => 'Hi, I have a question and would love your help.',
            self::Service => "Hi, I'd like to find out more about one of your services.",
            self::Doctor => "Hi, I'd like to find out more about one of your doctors.",
            self::Booking => "Hi, I'd like some help with booking an appointment.",
        };
    }
}
