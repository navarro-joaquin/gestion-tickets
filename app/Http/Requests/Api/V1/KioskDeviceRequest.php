<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Kiosks\KioskDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KioskDeviceRequest extends FormRequest
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
        $device = $this->route('kioskDevice');
        $deviceId = $device instanceof KioskDevice ? $device->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('kiosk_devices', 'slug')->ignore($deviceId),
            ],
            'is_active' => ['required', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [
                'required',
                'string',
                Rule::exists('sections', 'slug')->where('is_active', true),
            ],
        ];
    }
}
