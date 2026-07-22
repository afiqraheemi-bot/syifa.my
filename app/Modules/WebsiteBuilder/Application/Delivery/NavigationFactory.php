<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class NavigationFactory
{
    /** @return list<NavigationItem> */
    public function make(PublicWebsiteRenderModel $model, PublicSiteContext $context): array
    {
        $available = (new PublicRoutePolicy)->available($model, $context);
        // Home is intentionally excluded: the brand/logo is the canonical Home
        // link (see navbar.blade.php and footer.blade.php), and the Component
        // Catalogue caps Desktop Navigation at six primary items plus Booking.
        $labels = [
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
