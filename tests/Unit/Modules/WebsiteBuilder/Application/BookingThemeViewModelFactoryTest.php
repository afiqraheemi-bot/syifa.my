<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingThemeViewModelFactory;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use PHPUnit\Framework\TestCase;

final class BookingThemeViewModelFactoryTest extends TestCase
{
    public function test_it_maps_every_approved_website_template_to_the_shared_booking_theme(): void
    {
        $factory = new BookingThemeViewModelFactory;
        $templates = [
            'SYIFA_ESSENTIAL' => 'syifa-essential',
            'SYIFA_CARE' => 'syifa-care',
            'SYIFA_DENTAL' => 'syifa-dental',
            'SYIFA_AESTHETIC' => 'syifa-aesthetic',
            'SYIFA_SPECIALIST' => 'syifa-specialist',
        ];

        foreach ($templates as $storedTemplate => $renderedTemplate) {
            self::assertSame($renderedTemplate, $factory->make($storedTemplate)->templateId);
            self::assertSame($renderedTemplate, $factory->make($renderedTemplate)->templateId);
        }
    }

    public function test_it_rejects_an_unknown_template_instead_of_silently_using_essential(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);

        (new BookingThemeViewModelFactory)->make('unknown-template');
    }
}
