<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrinterRequest extends FormRequest
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
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'name' => ['required', 'string', 'max:80'],
            'driver' => ['required', 'string', Rule::in(['local', 'qz'])],
            'target' => ['nullable', 'string', 'max:255'],
            'paper_width' => ['required', 'integer', Rule::in([58, 80])],
            'is_active' => ['required', 'boolean'],
            'config' => ['nullable', 'array'],
        ];
    }
}
