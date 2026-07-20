<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Requests;

use App\Modules\ClinicRegistration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class TransitionClinicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'tenant_id' => ['prohibited'],
            'selected_add_on_references' => ['prohibited'],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
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
