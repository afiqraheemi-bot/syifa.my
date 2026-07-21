<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Presentation\Http\Requests;

use App\Modules\Commercial\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CancelCommercialOfferRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'tenant_id' => ['prohibited'],
            'payment' => ['prohibited'],
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
            'commercial.validation_failed',
            'Validation Failed',
            422,
            'The commercial request is invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
