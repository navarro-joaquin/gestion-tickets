<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'section' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('sections', 'slug'),
            ],
            'category' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('categories', 'slug'),
            ],
            'service' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('services', 'slug'),
            ],
            'station' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function filters(): ReportFilters
    {
        return ReportFilters::fromArray($this->validated());
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
