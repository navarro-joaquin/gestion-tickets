<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Priorities\PriorityProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriorityProfileRequest extends FormRequest
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
        $profile = $this->route('priorityProfile') ?? $this->route('priority_profile');
        $profileId = $profile instanceof PriorityProfile ? $profile->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('priority_profiles', 'name')->ignore($profileId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'general_ratio' => ['required', 'integer', 'min:1', 'max:20'],
            'preferential_ratio' => ['required', 'integer', 'min:0', 'max:20'],
            'aging_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_consecutive_preferential' => ['required', 'integer', 'min:1', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
