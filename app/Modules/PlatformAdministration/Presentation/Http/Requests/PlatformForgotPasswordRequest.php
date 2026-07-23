<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Requests;

use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PlatformForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetailsResponse::make(
            $this,
            'validation_failed',
            'Validation Failed',
            422,
            'The submitted input is invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
