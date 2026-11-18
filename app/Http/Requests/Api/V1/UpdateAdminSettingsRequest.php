<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Settings\NoShowPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'closing_time' => ['required', 'string', 'date_format:H:i'],
            'retention_months' => ['sometimes', 'integer', 'between:1,120'],
            'no_show_calls' => ['sometimes', 'integer', 'between:'.NoShowPolicy::MIN_CALLS.','.NoShowPolicy::MAX_CALLS],
            'no_show_minutes' => ['sometimes', 'integer', 'between:'.NoShowPolicy::MIN_MINUTES.','.NoShowPolicy::MAX_MINUTES],
        ];
    }
}
