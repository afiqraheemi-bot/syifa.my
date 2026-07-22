<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

enum PublicRoute: string
{
    case Home = 'home';
    case About = 'about';
    case Services = 'services';
    case Doctors = 'doctors';
    case Gallery = 'gallery';
    case Testimonials = 'testimonials';
    case Contact = 'contact';
    case Booking = 'booking';
    case Privacy = 'privacy';
    case Terms = 'terms';
}
