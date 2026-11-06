<?php

namespace Database\Seeders;

use App\Domain\Sections\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'general',
                'name' => 'Atención general',
                'is_preferential' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'pregnancy',
                'name' => 'Mujeres embarazadas',
                'is_preferential' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'elderly',
                'name' => 'Tercera edad',
                'is_preferential' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'disability',
                'name' => 'Discapacidad',
                'is_preferential' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
