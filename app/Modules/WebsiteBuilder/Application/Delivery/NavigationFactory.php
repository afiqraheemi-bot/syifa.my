<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class NavigationFactory
{
    /** @return list<NavigationItem> */
    public function make(
        PublicWebsiteRenderModel $model,
        PublicSiteContext $context,
        bool $bookingFlowAvailable = true,
        string $language = PublicContentLanguage::ENGLISH,
    ): array {
        $available = (new PublicRoutePolicy)->available(
            $model,
            $context,
            $bookingFlowAvailable,
        );
        // Home is intentionally excluded: the brand/logo is the canonical Home
        // link (see navbar.blade.php and footer.blade.php), and the Component
        // Catalogue caps Desktop Navigation at six primary items plus Booking.
        $labels = $language === PublicContentLanguage::MALAY ? [
            PublicRoute::About->value => 'Tentang Kami', PublicRoute::Services->value => 'Servis',
            PublicRoute::Doctors->value => 'Doktor', PublicRoute::Gallery->value => 'Galeri', PublicRoute::Testimonials->value => 'Testimoni',
            PublicRoute::Contact->value => 'Hubungi', PublicRoute::Booking->value => 'Tempah Appointment',
        ] : [
            PublicRoute::About->value => 'About', PublicRoute::Services->value => 'Services',
            PublicRoute::Doctors->value => 'Doctors', PublicRoute::Gallery->value => 'Gallery', PublicRoute::Testimonials->value => 'Testimonials',
            PublicRoute::Contact->value => 'Contact', PublicRoute::Booking->value => 'Book Appointment',
        ];
        $items = [];
        foreach ($labels as $route => $label) {
            if (isset($available[$route])) {
                $items[] = new NavigationItem(PublicRoute::from($route), $label, $available[$route]);
            }
        }

        return $items;
    }
}
