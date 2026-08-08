<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\ClinicContact\OptionalContactValue;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileCommand;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicContactProfileException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * Updates the WhatsApp field of the Clinic-owned Contact Profile (ADR-023).
 * Rendered and edited inline from the Website Content page
 * (TenantManagement/Website/ClinicOwnerWebsiteContentOverview.vue) so Clinic
 * Owners can change it and preview the result without leaving that page —
 * there is no separate "show" page for this controller.
 */
final readonly class ClinicOwnerContactSettingsController
{
    public function __invoke(Request $request, UpdateClinicContactProfileService $contactProfile): RedirectResponse
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner Contact settings tenant context was not established.');
        }

        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $authorization = new WebsiteAuthorizationContext($context->identityId, $context->role, actorTenantId: $context->tenantId);
        $current = $contactProfile->read($context->tenantId, $authorization);
        $whatsAppNumber = trim((string) ($data['whatsapp_number'] ?? ''));

        try {
            $contactProfile->handle(new UpdateClinicContactProfileCommand(
                $context->tenantId,
                $current->clinicId,
                $authorization,
                OptionalContactValue::omitted(),
                OptionalContactValue::omitted(),
                OptionalContactValue::omitted(),
                $whatsAppNumber === '' ? OptionalContactValue::clear() : OptionalContactValue::with($whatsAppNumber),
                OptionalContactValue::omitted(),
                OptionalContactValue::omitted(),
                new DateTimeImmutable,
                (string) Str::uuid(),
                (int) $data['version'],
            ));
        } catch (StaleClinicWriteException) {
            return back()->withErrors([
                'whatsapp_number' => 'Your WhatsApp setting changed elsewhere while you were editing. Refresh and try again.',
            ]);
        } catch (InvalidClinicContactProfileException $exception) {
            return back()->withErrors([
                'whatsapp_number' => $exception->getMessage(),
            ]);
        }

        return back()->with('contact_settings_saved', true);
    }
}
