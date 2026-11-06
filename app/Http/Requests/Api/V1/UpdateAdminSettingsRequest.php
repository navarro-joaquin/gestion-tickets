<?php

namespace App\Http\Requests\Api\V1;

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
        ];
    }
}
