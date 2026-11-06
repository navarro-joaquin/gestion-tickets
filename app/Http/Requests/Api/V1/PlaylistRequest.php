<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaylistRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['present', 'array'],
            'items.*.media_asset_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('media_assets', 'id'),
            ],
            'items.*.duration_seconds' => ['nullable', 'integer', 'between:1,3600'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
