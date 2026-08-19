<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Notification\Application\BookingWhatsAppSettingsService;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

final readonly class ClinicOwnerBookingWhatsAppSettingsController
{
    public function __invoke(Request $request, BookingWhatsAppSettingsService $settings): RedirectResponse
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner Booking WhatsApp settings context was not established.');
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'recipient_number' => ['nullable', 'string', 'max:40'],
        ]);

        if ((bool) $data['enabled'] && ! $settings->providerConfigured()) {
            return back()->withErrors([
                'enabled' => 'WhatsApp Business API must be configured by the platform before notifications can be enabled.',
            ]);
        }

        try {
            $settings->update(
                $context->tenantId,
                (bool) $data['enabled'],
                isset($data['recipient_number']) ? (string) $data['recipient_number'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['recipient_number' => $exception->getMessage()]);
        }

        return back()->with('booking_whatsapp_settings_saved', true);
    }
}
