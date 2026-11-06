<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Kiosks\KioskDevice;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'kiosk' => [
                'bail',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        $fail('El token del quiosco no es válido.');

                        return;
                    }

                    $exists = KioskDevice::query()
                        ->where('token', $value)
                        ->where('is_active', true)
                        ->exists();

                    if (! $exists) {
                        $fail('El token del quiosco no es válido.');
                    }
                },
            ],
            'section' => [
                'bail',
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
            'category' => [
                'bail',
                'nullable',
                'required_without:service',
                'string',
                Rule::exists('categories', 'slug'),
            ],
            'service' => [
                'bail',
                'nullable',
                'required_without:category',
                'string',
                Rule::exists('services', 'slug'),
            ],
        ];
    }

    /**
     * Comprueba que la sección solicitada esté habilitada en el quiosco.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $kiosk = KioskDevice::query()
                ->where('token', $this->input('kiosk'))
                ->where('is_active', true)
                ->first();

            if ($kiosk === null) {
                return;
            }

            $sectionEnabled = $kiosk->sections()
                ->where('sections.slug', $this->input('section'))
                ->where('sections.is_active', true)
                ->exists();

            if (! $sectionEnabled) {
                $validator->errors()->add(
                    'section',
                    'La sección no está habilitada para este quiosco.',
                );
            }
        });
    }
}
