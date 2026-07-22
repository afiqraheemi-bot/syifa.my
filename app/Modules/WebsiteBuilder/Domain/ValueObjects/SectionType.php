<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum SectionType: string
{
    case Hero = 'HERO';
    case About = 'ABOUT';
    case Services = 'SERVICES';
    case Doctors = 'DOCTORS';
    case Testimonials = 'TESTIMONIALS';
    case Gallery = 'GALLERY';
    case Faq = 'FAQ';
    case Contact = 'CONTACT';
    case BookingCta = 'BOOKING_CTA';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWebsiteValueException('Stored Website Section type is invalid.');
    }
}
