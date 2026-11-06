<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateQueueRequest extends FormRequest
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
            'profile_id' => [
                'nullable',
                'integer',
                Rule::exists('priority_profiles', 'id'),
            ],
            'general_count' => ['required', 'integer', 'min:0', 'max:200'],
            'preferential_count' => ['required', 'integer', 'min:0', 'max:200'],
        ];
    }
}
