<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Availability;

use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use DateTimeImmutable;
use DateTimeZone;

final class ClinicSlotGenerator
{
    /** @return list<GeneratedClinicSlot> */
    public function generate(ClinicOperationalTimeData $clinic, string $localDate): array
    {
        $date = new AppointmentDate($localDate);
        $duration = $clinic->appointmentDurationMinutes;
        if ($duration === null || $clinic->bookingCapacityPerSlot === null) {
            throw new InvalidClinicBookingConfigurationException('Clinic Booking Configuration is unavailable.');
        }

        $timezone = new DateTimeZone($clinic->timezone);
        $day = (int) (new DateTimeImmutable($date->value.' 00:00:00', $timezone))->format('N');
        $slots = [];

        foreach ($clinic->operatingIntervals as $interval) {
            if ($interval->dayOfWeek !== $day) {
                continue;
            }
            $cursor = $this->localInstant($date->value, $interval->opensAt, $timezone);
            $closing = $this->localInstant($date->value, $interval->closesAt, $timezone);
            while ($cursor->modify(sprintf('+%d minutes', $duration)) <= $closing) {
                $end = $cursor->modify(sprintf('+%d minutes', $duration));
                $slots[] = new GeneratedClinicSlot(
                    $date->value,
                    $cursor->format('H:i'),
                    $end->format('H:i'),
                    $clinic->timezone,
                    $cursor->setTimezone(new DateTimeZone('UTC')),
                    $end->setTimezone(new DateTimeZone('UTC')),
                    $duration,
                );
                $cursor = $end;
            }
        }

        return $slots;
    }

    public function exact(ClinicOperationalTimeData $clinic, string $localDate, string $localStart): GeneratedClinicSlot
    {
        foreach ($this->generate($clinic, $localDate) as $slot) {
            if ($slot->localStart === $localStart) {
                return $slot;
            }
        }

        throw new InvalidClinicBookingConfigurationException('The requested appointment is not an available generated slot boundary.');
    }

    private function localInstant(string $date, string $time, DateTimeZone $timezone): DateTimeImmutable
    {
        $text = $date.' '.$time;
        $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, $timezone);
        if ($local === false || $local->format('Y-m-d H:i') !== $text) {
            throw new InvalidClinicBookingConfigurationException('Clinic-local time conversion is invalid.');
        }

        $naive = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, new DateTimeZone('UTC'));
        if ($naive === false) {
            throw new InvalidClinicBookingConfigurationException('Clinic-local time conversion is invalid.');
        }
        $matches = [];
        foreach ($timezone->getTransitions($local->getTimestamp() - 10800, $local->getTimestamp() + 10800) ?: [] as $transition) {
            $candidate = (new DateTimeImmutable('@'.($naive->getTimestamp() - $transition['offset'])))->setTimezone($timezone);
            if ($candidate->format('Y-m-d H:i') === $text) {
                $matches[$candidate->getTimestamp()] = true;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidClinicBookingConfigurationException('Clinic-local time is ambiguous or invalid.');
        }

        return $local;
    }
}
