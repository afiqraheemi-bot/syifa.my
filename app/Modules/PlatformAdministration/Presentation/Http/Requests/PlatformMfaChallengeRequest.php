<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlatformMfaChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
