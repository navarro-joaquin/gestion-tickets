<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Displays\Display;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisplayRequest extends FormRequest
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
        $display = $this->route('display');
        $displayId = $display instanceof Display ? $display->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('displays', 'slug')->ignore($displayId),
            ],
            'layout' => [
                'required',
                Rule::in([Display::LAYOUT_QUEUE, Display::LAYOUT_MIXED, Display::LAYOUT_MEDIA]),
            ],
            'is_active' => ['required', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'playlists' => ['nullable', 'array'],
            'playlists.*' => ['integer', Rule::exists('playlists', 'id')],
        ];
    }
}
