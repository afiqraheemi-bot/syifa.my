<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Requests;

use App\Modules\ClinicRegistration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class SelectInitialCommercialOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'plan_offering_id' => ['required', 'uuid'],
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'currency' => ['prohibited'],
            'provider' => ['prohibited'],
            'provider_key' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'registration_id' => ['prohibited'],
            'subscription_id' => ['prohibited'],
            'platform_identity_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'payment_attempt_id' => ['prohibited'],
            'attempt_id' => ['prohibited'],
        ];
    }

    public function planOfferingId(): string
    {
        return (string) $this->validated('plan_offering_id');
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetailsResponse::make(
            $this,
            'clinic_registration.validation_failed',
            'Validation Failed',
            422,
            'The commercial offer selection is invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
