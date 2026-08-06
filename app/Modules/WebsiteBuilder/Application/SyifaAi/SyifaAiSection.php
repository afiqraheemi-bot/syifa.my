<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

enum SyifaAiSection: string
{
    case Hero = 'HERO';
    case About = 'ABOUT';
    case Services = 'SERVICES';
    case Doctors = 'DOCTORS';
    case Faq = 'FAQ';
    case Contact = 'CONTACT';
}
