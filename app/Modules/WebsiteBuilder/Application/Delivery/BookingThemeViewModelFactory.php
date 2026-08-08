<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\BookingThemeViewModel;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;

/**
 * The single template-to-booking-theme mapping used by public and protected
 * booking delivery. Keeping this mapping here prevents individual clinics or
 * routes from silently drifting back to the default template.
 */
final readonly class BookingThemeViewModelFactory
{
    public function make(
        ?string $templateId,
        ?string $primaryColor = null,
        ?string $secondaryColor = null,
    ): BookingThemeViewModel {
        $template = $this->template($templateId);

        return new BookingThemeViewModel(
            strtolower(str_replace('_', '-', $template->value)),
            (new BrandTokenResolver)->resolve(
                $primaryColor ?? '#176B50',
                $secondaryColor ?? '#E8F0EA',
            ),
        );
    }

    private function template(?string $templateId): TemplateId
    {
        if ($templateId === null || trim($templateId) === '') {
            return TemplateId::SyifaEssential;
        }

        $normalized = strtoupper(str_replace('-', '_', trim($templateId)));

        return TemplateId::fromStored($normalized);
    }
}
