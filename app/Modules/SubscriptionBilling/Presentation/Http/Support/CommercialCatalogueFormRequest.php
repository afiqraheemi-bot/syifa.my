<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

abstract class CommercialCatalogueFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    abstract public function rules(): array;

    /**
     * @return list<string>
     */
    abstract protected function allowedFields(): array;

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $this->allowedFields(), true)) {
                    $validator->errors()->add($field, 'This field is not accepted.');
                }
            }
        }];
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
        $mapper = new CommercialCatalogueErrorResponseMapper;
        $problem = $mapper->map(
            new \InvalidArgumentException('Validation failed.'),
            $this->correlationId(),
            $this->path(),
            $validator->errors()->toArray(),
        );

        throw new HttpResponseException(CommercialCatalogueProblemDetailsResponseFactory::make($problem));
    }
}
