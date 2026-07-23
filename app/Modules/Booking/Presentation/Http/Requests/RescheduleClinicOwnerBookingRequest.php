<?php

declare(strict_types=1);

namespace App\Modules\Booking\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleClinicOwnerBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
        ];
    }
}
