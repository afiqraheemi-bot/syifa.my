<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;

final readonly class ContactActionFactory
{
    public function make(FooterRenderModel $contact, WhatsAppDeliveryIntent $whatsAppIntent = WhatsAppDeliveryIntent::GeneralEnquiry): ContactActionSet
    {
        $phone = $contact->contactPhone === null ? null : 'tel:'.rawurlencode($contact->contactPhone);
        $email = $contact->contactEmail === null ? null : 'mailto:'.rawurlencode($contact->contactEmail);
        $whatsApp = $contact->whatsAppNumber === null ? null : new PublicUrl(
            'https://wa.me/'.rawurlencode(ltrim($contact->whatsAppNumber, '+')).'?text='.rawurlencode($whatsAppIntent->localizedMessage())
        );
        $directions = null;
        if ($contact->latitude !== null && $contact->longitude !== null) {
            $directions = new PublicUrl('https://www.google.com/maps/search/?api=1&query='.rawurlencode($contact->latitude.','.$contact->longitude));
        } elseif ($contact->address !== null) {
            $directions = new PublicUrl('https://www.google.com/maps/search/?api=1&query='.rawurlencode($contact->address));
        }

        return new ContactActionSet($phone, $email, $whatsApp, $directions);
    }
}
