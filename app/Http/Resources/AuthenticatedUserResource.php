<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => $this->is_active,
            'default_section' => $this->defaultSection ? [
                'slug' => $this->defaultSection->slug,
                'name' => $this->defaultSection->name,
            ] : null,
            'default_station' => $this->defaultStation ? [
                'id' => $this->defaultStation->id,
                'name' => $this->defaultStation->name,
            ] : null,
        ];
    }
}
