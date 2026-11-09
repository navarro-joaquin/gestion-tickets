<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Operations\Station;
use App\Domain\Sections\Section;
use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
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
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;
        $requiresDefaultAssignment = $this->requiresDefaultAssignment();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'string',
                Password::min(8),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'default_section' => [
                Rule::requiredIf($requiresDefaultAssignment),
                'nullable',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'default_station_id' => [
                Rule::requiredIf($requiresDefaultAssignment),
                'nullable',
                'integer',
                Rule::exists('stations', 'id')->where('is_active', true),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->requiresDefaultAssignment()) {
                return;
            }

            $sectionSlug = $this->input('default_section');
            $stationId = $this->input('default_station_id');

            if (! is_string($sectionSlug) || $sectionSlug === '' || ! is_numeric($stationId)) {
                return;
            }

            $sectionId = Section::query()
                ->where('slug', $sectionSlug)
                ->value('id');

            $stationBelongsToSection = Station::query()
                ->whereKey((int) $stationId)
                ->where('section_id', $sectionId)
                ->exists();

            if (! $stationBelongsToSection) {
                $validator->errors()->add(
                    'default_station_id',
                    'La ventanilla por defecto debe pertenecer a la sección seleccionada.',
                );
            }
        });
    }

    private function requiresDefaultAssignment(): bool
    {
        return in_array(
            $this->input('role'),
            [UserRole::Operator->value, UserRole::Supervisor->value],
            true,
        );
    }
}
