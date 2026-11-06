<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Operations\Station;
use App\Domain\Sections\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StationRequest extends FormRequest
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
        $station = $this->route('station');
        $stationId = $station instanceof Station ? $station->id : null;
        $sectionId = $this->sectionId();

        return [
            'section' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('stations', 'name')
                    ->where('section_id', $sectionId ?? $station?->section_id)
                    ->ignore($stationId),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function sectionId(): ?int
    {
        $slug = $this->input('section');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Section::query()->where('slug', $slug)->value('id');
    }
}
