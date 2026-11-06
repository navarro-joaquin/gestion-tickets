<?php

namespace App\Http\Resources;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class AdminSectionResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'matrix_confirmed_at' => $this->matrix_confirmed_at?->toIso8601String(),
            'matrix_confirmed_by' => $this->matrix_confirmed_by,
            'stations_count' => $this->whenCounted('stations'),
            'codes' => $this->whenLoaded(
                'ticketCodes',
                fn (): array => $this->ticketCodes
                    ->map(fn (SectionTicketCode $code): array => [
                        'id' => $code->id,
                        'code' => $code->code,
                        'sort_order' => $code->sort_order,
                        'is_active' => $code->is_active,
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
        ];
    }
}
