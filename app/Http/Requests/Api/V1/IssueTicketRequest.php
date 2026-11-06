<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTicketRequest extends FormRequest
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
            'section' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'category' => [
                'nullable',
                'required_without:service',
                'string',
                Rule::exists('categories', 'slug'),
            ],
            'service' => [
                'nullable',
                'required_without:category',
                'string',
                Rule::exists('services', 'slug'),
            ],
        ];
    }
}
