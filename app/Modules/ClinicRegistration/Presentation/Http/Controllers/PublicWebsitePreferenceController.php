<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\InvalidPublicWebsiteAddressException;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\PublicWebsiteAddressAvailabilityInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PublicWebsitePreferenceController
{
    public function __invoke(
        Request $request,
        PublicWebsiteAddressAvailabilityInterface $addresses,
        RegistrationTrackingCredentialInterface $tracking,
    ): JsonResponse {
        /** @var array{subdomain: string} $validated */
        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:63'],
        ]);

        try {
            $subdomain = strtolower(trim($validated['subdomain']));
            $available = $addresses->available($subdomain, $tracking->establish());
        } catch (InvalidPublicWebsiteAddressException) {
            return response()->json(['available' => false, 'message' => 'Alamat Website tidak sah.'], 422);
        }

        return response()->json([
            'available' => $available,
            'message' => $available ? 'Alamat Website tersedia.' : 'Alamat Website telah digunakan.',
        ]);
    }
}
