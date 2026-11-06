<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Media\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaAssetRequest extends FormRequest
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
        $mimeRule = $this->input('type') === MediaAsset::TYPE_VIDEO
            ? 'mimetypes:video/mp4,video/webm'
            : 'mimetypes:image/jpeg,image/png,image/webp,image/gif';

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([MediaAsset::TYPE_IMAGE, MediaAsset::TYPE_VIDEO])],
            'duration_seconds' => ['nullable', 'integer', 'between:1,3600'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => [
                $this->isMethod('post') ? 'required' : 'prohibited',
                'file',
                'max:194560',
                $mimeRule,
            ],
        ];
    }
}
