<?php

namespace App\Http\Resources;

use App\Domain\Audit\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'before' => $this->before,
            'after' => $this->after,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded(
                'user',
                fn (): ?array => $this->user === null ? null : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
            ),
        ];
    }
}
