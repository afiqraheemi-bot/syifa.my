<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Requests;

use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

final class PlatformSessionLoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'correlation_id' => ['nullable', 'uuid'],
        ];
    }

    public function correlationId(): string
    {
        $correlationId = $this->attributes->get('correlation_id');

        if (is_string($correlationId) && $correlationId !== '') {
            return $correlationId;
        }

        $bodyCorrelationId = $this->string('correlation_id')->toString();

        if ($bodyCorrelationId !== '') {
            return $bodyCorrelationId;
        }

        return (string) Str::uuid();
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetailsResponse::make(
            $this,
            'validation_failed',
            'Validation Failed',
            422,
            'The submitted authentication input is invalid.',
            $validator->errors()->toArray(),
        ));
    }
}
