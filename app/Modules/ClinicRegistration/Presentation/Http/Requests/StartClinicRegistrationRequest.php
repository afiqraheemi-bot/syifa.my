<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Requests;

use App\Modules\ClinicRegistration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StartClinicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'selected_add_on_references' => ['prohibited'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetailsResponse::make(
            $this,
            'clinic_registration.validation_failed',
            'Validation Failed',
            422,
            'The clinic registration request is invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
