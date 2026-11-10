<?php

namespace App\Http\Resources;

use App\Domain\Sections\Category;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'matrix_confirmed_at' => $this->matrix_confirmed_at?->toIso8601String(),
            'codes' => $this->whenLoaded(
                'ticketCodes',
                fn (): array => $this->ticketCodes
                    ->map(fn (SectionTicketCode $code): array => [
                        'code' => $code->code,
                        'categories' => $code->categories
                            ->map(fn (Category $category): array => [
                                'slug' => $category->slug,
                                'name' => $category->name,
                                'is_preferential' => $category->is_preferential,
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ),
            'services' => $this->whenLoaded(
                'services',
                fn (): array => $this->services
                    ->filter(fn (Service $service): bool => $service->isAvailableAt(now()))
                    ->map(fn (Service $service): array => [
                        'id' => $service->id,
                        'slug' => $service->slug,
                        'name' => $service->name,
                        'label' => $service->label,
                        'description' => $service->description,
                        'available_from' => $service->available_from,
                        'available_to' => $service->available_to,
                        'available_days' => $service->available_days,
                        'category' => $service->category === null ? null : [
                            'slug' => $service->category->slug,
                            'name' => $service->category->name,
                            'is_preferential' => $service->category->is_preferential,
                        ],
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
