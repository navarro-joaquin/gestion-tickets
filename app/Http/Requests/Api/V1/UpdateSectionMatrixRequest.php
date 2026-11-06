<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionMatrixRequest extends FormRequest
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
            'codes' => ['required', 'array', 'size:3'],
            'codes.*.code' => ['required', 'string', 'size:1', 'distinct'],
            'codes.*.categories' => ['required', 'array', 'min:1'],
            'codes.*.categories.*' => [
                'required',
                'string',
                Rule::exists('categories', 'slug'),
            ],
        ];
    }
}
