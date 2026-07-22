<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class TestimonialsSectionRenderModel implements SectionRenderContract
{
    /** @param list<TestimonialRenderModel> $testimonials */
    public function __construct(public array $testimonials) {}

    public function type(): string
    {
        return 'TESTIMONIALS';
    }
}
