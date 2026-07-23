<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Contracts\Queries\AvailableSlotData;
use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sprint 2: the real adapter behind `PublicAvailabilityReaderInterface`
 * (ADR-028/029), wrapping the Booking Engine's existing, already-tested
 * `AvailableSlotReaderInterface`. Replaces Sprint 1's temporary fixture
 * with no change to `PublicAvailabilityReaderInterface`'s contract, no
 * `@throws` — every Booking Engine exception is translated into ADR-028's
 * closed vocabulary here, never propagated, so `AvailabilityDeliveryService`,
 * every ViewModel, every Controller, and every Blade view are unchanged.
 *
 * `AvailableSlotData->available` (bool) maps directly onto
 * `Available`/`Unavailable` — the exact mapping ADR-028 already named as
 * requiring no new boolean-to-enum logic inside the Engine. A Clinic with no
 * operating hours configured for the requested day (`ClinicOperationalTimeNotFoundException`)
 * or an incomplete Booking configuration (`InvalidClinicBookingConfigurationException`)
 * is a Business Rule condition (ADR-028's Error Contract) — silently no
 * slots that day, never surfaced as an error. Any other, unexpected failure
 * is an Infrastructure condition — logged, and represented as a single
 * `Unknown` slot spanning the day, an honest admission no signal exists
 * (ADR-028), never silently rendered as Available or Unavailable.
 */
final readonly class BookingAvailableSlotReaderAdapter implements PublicAvailabilityReaderInterface
{
    public function __construct(private AvailableSlotReaderInterface $slots) {}

    public function forDate(string $trustedTenantId, string $localDate): array
    {
        try {
            return array_map(
                static fn (AvailableSlotData $slot): PublicAvailabilitySlot => new PublicAvailabilitySlot(
                    $localDate,
                    $slot->localStart,
                    $slot->localEnd,
                    $slot->timezone,
                    $slot->available ? PublicAvailabilityState::Available : PublicAvailabilityState::Unavailable,
                ),
                $this->slots->forDate($trustedTenantId, $localDate),
            );
        } catch (ClinicOperationalTimeNotFoundException|InvalidClinicBookingConfigurationException) {
            return [];
        } catch (Throwable $exception) {
            Log::error('Public availability signal unobtainable.', [
                'tenant_id' => $trustedTenantId,
                'local_date' => $localDate,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [new PublicAvailabilitySlot($localDate, '00:00', '23:59', 'UTC', PublicAvailabilityState::Unknown)];
        }
    }
}
