<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Presentation\Http\Requests;

use App\Modules\AcquisitionOffer\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PrepareCommercialOfferRequest extends FormRequest
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
            'clinic_registration_id' => ['required', 'uuid'],
            'plan_offering_id' => ['required', 'string', 'max:120'],
            'tenant_id' => ['prohibited'],
            'add_on_selections' => ['prohibited'],
            'payment_method' => ['prohibited'],
        ];
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
