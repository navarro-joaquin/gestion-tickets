<?php

namespace Database\Seeders;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Matriz confirmada en el acta del 2026-11-06.
     *
     * @var array<int, array{
     *     slug: string,
     *     name: string,
     *     sort_order: int,
     *     codes: array<string, array<int, string>>
     * }>
     */
    private const SECTIONS = [
        [
            'slug' => 'plataforma',
            'name' => 'Plataforma de Atención al Cliente',
            'sort_order' => 1,
            'codes' => [
                'A' => ['general'],
                'B' => ['elderly', 'disability'],
                'C' => ['pregnancy'],
            ],
        ],
        [
            'slug' => 'odeco',
            'name' => 'ODECO',
            'sort_order' => 2,
            'codes' => [
                'D' => ['general'],
                'E' => ['elderly', 'disability'],
                'F' => ['pregnancy'],
            ],
        ],
        [
            'slug' => 'cajas',
            'name' => 'Cajas',
            'sort_order' => 3,
            'codes' => [
                'G' => ['general'],
                'H' => ['elderly', 'disability'],
                'I' => ['pregnancy'],
            ],
        ],
    ];

    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id', 'slug');

        foreach (self::SECTIONS as $sectionData) {
            $section = Section::query()->updateOrCreate(
                ['slug' => $sectionData['slug']],
                [
                    'name' => $sectionData['name'],
                    'is_active' => true,
                    'sort_order' => $sectionData['sort_order'],
                    'matrix_confirmed_at' => '2026-11-06 00:00:00',
                    'matrix_confirmed_by' => 'Alvaro Navarro',
                ],
            );

            $position = 0;

            foreach ($sectionData['codes'] as $code => $categorySlugs) {
                $position++;

                $ticketCode = SectionTicketCode::query()->updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'code' => $code,
                    ],
                    [
                        'sort_order' => $position,
                        'is_active' => true,
                    ],
                );

                $ticketCode->categories()->sync(
                    collect($categorySlugs)
                        ->map(fn (string $slug): int => $categoryIds[$slug])
                        ->all(),
                );
            }
        }
    }
}
