<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Requests;

use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CreateClinicOwnerSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
            'remember' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'authority_id' => ['prohibited'],
            'identity_id' => ['prohibited'],
            'role' => ['prohibited'],
            'permissions' => ['prohibited'],
            'remember_me' => ['prohibited'],
            'redirect' => ['prohibited'],
            'redirect_url' => ['prohibited'],
            'mfa' => ['prohibited'],
            'mfa_code' => ['prohibited'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['email', 'password', 'remember', '_token'];
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, 'This field is not accepted.');
                }
            }
        }];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetailsResponse::make(
            $this,
            'validation_failed',
            'Validation Failed',
            422,
            'The submitted session credentials are invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
