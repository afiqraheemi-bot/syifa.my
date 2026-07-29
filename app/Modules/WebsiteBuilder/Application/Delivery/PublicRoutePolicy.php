<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class PublicRoutePolicy
{
    /** @return array<string, PublicUrl> */
    public function available(
        PublicWebsiteRenderModel $model,
        PublicSiteContext $context,
        bool $bookingFlowAvailable = true,
    ): array {
        $routes = [PublicRoute::Home->value => $context->url()];
        $availableTypes = array_fill_keys(array_map(static fn ($section): string => $section->type(), $model->sections), true);
        foreach ([
            PublicRoute::About->value => 'ABOUT', PublicRoute::Services->value => 'SERVICES', PublicRoute::Doctors->value => 'DOCTORS',
            PublicRoute::Gallery->value => 'GALLERY', PublicRoute::Testimonials->value => 'TESTIMONIALS', PublicRoute::Contact->value => 'CONTACT',
            PublicRoute::Booking->value => 'BOOKING_CTA',
        ] as $route => $type) {
            if (isset($availableTypes[$type])) {
                $routes[$route] = $route === PublicRoute::Booking->value && $bookingFlowAvailable
                    ? $context->url('booking')
                    : new PublicUrl($context->url()->value.'#'.$route);
            }
        }
        $routes[PublicRoute::Privacy->value] = $context->url('privacy');
        $routes[PublicRoute::Terms->value] = $context->url('terms');

        return $routes;
    }
}
