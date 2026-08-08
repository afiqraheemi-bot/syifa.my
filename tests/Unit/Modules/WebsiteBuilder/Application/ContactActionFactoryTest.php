<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\ContactActionFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\WhatsAppDeliveryIntent;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactActionFactoryTest extends TestCase
{
    public function test_whatsapp_action_is_absent_when_no_number_is_published(): void
    {
        $actions = (new ContactActionFactory)->make($this->contact(whatsAppNumber: null));

        self::assertNull($actions->whatsApp);
    }

    public function test_whatsapp_action_defaults_to_the_general_enquiry_delivery_intent(): void
    {
        $actions = (new ContactActionFactory)->make($this->contact());

        self::assertSame(
            'https://wa.me/60123456789?text=Hi%2C%20I%20have%20a%20question%20and%20would%20love%20your%20help.',
            $actions->whatsApp?->value,
        );
    }

    #[DataProvider('deliveryIntents')]
    public function test_every_governed_delivery_intent_resolves_to_its_own_localized_message(WhatsAppDeliveryIntent $intent, string $expectedMessage): void
    {
        $actions = (new ContactActionFactory)->make($this->contact(), $intent);

        self::assertSame('https://wa.me/60123456789?text='.rawurlencode($expectedMessage), $actions->whatsApp?->value);
    }

    /** @return iterable<string, array{WhatsAppDeliveryIntent, string}> */
    public static function deliveryIntents(): iterable
    {
        yield 'general enquiry' => [WhatsAppDeliveryIntent::GeneralEnquiry, 'Hi, I have a question and would love your help.'];
        yield 'service' => [WhatsAppDeliveryIntent::Service, "Hi, I'd like to find out more about one of your services."];
        yield 'doctor' => [WhatsAppDeliveryIntent::Doctor, "Hi, I'd like to find out more about one of your doctors."];
        yield 'booking' => [WhatsAppDeliveryIntent::Booking, "Hi, I'd like some help with booking an appointment."];
    }

    public function test_the_generated_url_never_contains_a_raw_space_or_comma(): void
    {
        $actions = (new ContactActionFactory)->make($this->contact());

        self::assertStringNotContainsString(' ', $actions->whatsApp?->value ?? '');
        self::assertStringNotContainsString(',', $actions->whatsApp?->value ?? '');
    }

    public function test_the_leading_plus_is_stripped_for_the_wa_me_path_segment(): void
    {
        $actions = (new ContactActionFactory)->make($this->contact(whatsAppNumber: '+60198765432'));

        self::assertStringStartsWith('https://wa.me/60198765432?text=', $actions->whatsApp?->value ?? '');
    }

    private function contact(?string $whatsAppNumber = '+60123456789'): FooterRenderModel
    {
        return new FooterRenderModel(
            'Klinik Syifa',
            'hello@clinic.test',
            '+6012',
            'Kuala Lumpur',
            [],
            [],
            $whatsAppNumber,
            null,
            null,
        );
    }
}
