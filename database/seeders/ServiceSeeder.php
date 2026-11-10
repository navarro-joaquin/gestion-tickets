<?php

namespace Database\Seeders;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Opciones de atención que antes eran categorías globales y ahora se
     * administran como servicios del catálogo de cada sección.
     *
     * @var array<int, array{slug: string, name: string, category: string, sort_order: int}>
     */
    private const SERVICES = [
        ['slug' => 'general', 'name' => 'General', 'category' => 'general', 'sort_order' => 1],
        ['slug' => 'tercera-edad', 'name' => 'Tercera edad', 'category' => 'elderly', 'sort_order' => 2],
        ['slug' => 'discapacitados', 'name' => 'Discapacitados', 'category' => 'disability', 'sort_order' => 3],
        ['slug' => 'embarazadas', 'name' => 'Embarazadas', 'category' => 'pregnancy', 'sort_order' => 4],
    ];

    public function run(): void
    {
        $categories = Category::query()
            ->whereIn('slug', array_column(self::SERVICES, 'category'))
            ->pluck('id', 'slug');

        Section::query()
            ->orderBy('sort_order')
            ->each(function (Section $section) use ($categories): void {
                foreach (self::SERVICES as $service) {
                    if (! isset($categories[$service['category']])) {
                        continue;
                    }

                    Service::query()->updateOrCreate(
                        [
                            'section_id' => $section->id,
                            'slug' => $service['slug'],
                        ],
                        [
                            'category_id' => $categories[$service['category']],
                            'name' => $service['name'],
                            'label' => '',
                            'description' => null,
                            'is_active' => true,
                            'available_days' => null,
                            'available_from' => null,
                            'available_to' => null,
                            'effective_from' => null,
                            'effective_to' => null,
                            'sort_order' => $service['sort_order'],
                        ],
                    );
                }
            });
    }
}
