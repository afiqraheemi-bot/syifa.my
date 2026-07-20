<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Requests;

use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\ClinicRegistration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class UpdateClinicRegistrationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'clinic_name' => ['nullable', 'string', 'max:200'],
            'clinic_email' => ['nullable', 'string', 'email', 'max:254'],
            'clinic_phone' => ['nullable', 'string', 'max:40'],
            'clinic_address' => ['nullable', 'string', 'max:1000'],
            'selected_plan_offering_reference' => ['nullable', 'string', 'max:120'],
            'selected_billing_option_reference' => ['nullable', 'string', 'max:120'],
            'commercial_snapshot_version' => ['nullable', 'string', 'max:64'],
            'declarations' => ['array'],
            'declarations.*.key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/'],
            'declarations.*.version' => ['required', 'string', 'max:64'],
            'declarations.*.accepted_at' => ['required', 'date'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'tenant_id' => ['prohibited'],
            'selected_add_on_references' => ['prohibited'],
        ];
    }

    /** @return list<DeclarationAcceptanceData> */
    public function declarations(): array
    {
        $declarations = $this->validated('declarations', []);
        if (! is_array($declarations)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $declaration): DeclarationAcceptanceData => new DeclarationAcceptanceData(
                (string) $declaration['key'],
                (string) $declaration['version'],
                (string) $declaration['accepted_at'],
            ),
            $declarations,
        ));
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
