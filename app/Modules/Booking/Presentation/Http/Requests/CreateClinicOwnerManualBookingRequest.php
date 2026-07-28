<?php

declare(strict_types=1);

namespace App\Modules\Booking\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateClinicOwnerManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(['phone', 'whatsapp', 'walk_in', 'staff'])],
            'patient_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:254'],
            'notes' => ['nullable', 'string'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'service_id' => ['nullable', 'uuid'],
        ];
    }
}
