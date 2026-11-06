<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
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
        $service = $this->route('service');
        $serviceId = $service instanceof Service ? $service->id : null;

        return [
            'section' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('services', 'slug')
                    ->where('section_id', $this->sectionId())
                    ->ignore($serviceId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'available_days' => ['nullable', 'array'],
            'available_days.*' => ['integer', 'between:1,7'],
            'available_from' => ['nullable', 'date_format:H:i'],
            'available_to' => ['nullable', 'date_format:H:i'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'operator_ids' => ['nullable', 'array'],
            'operator_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
        ];
    }

    private function sectionId(): ?int
    {
        return Section::query()
            ->where('slug', $this->input('section'))
            ->value('id');
    }
}
