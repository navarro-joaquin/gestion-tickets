<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Sections\Section;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SectionSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('t-map')]
class SectionControllerTest extends TestCase
{
    public function test_index_returns_only_active_sections_ordered_by_sort_order(): void
    {
        $this->seedMatrix();

        Section::query()->where('slug', 'cajas')->sole()->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/sections');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.slug', 'plataforma');
        $response->assertJsonPath('data.1.slug', 'odeco');
        $response->assertJsonMissingPath('data.2');
    }

    public function test_index_exposes_codes_with_their_categories(): void
    {
        $this->seedMatrix();

        $response = $this->getJson('/api/v1/sections');

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', 'plataforma');
        $response->assertJsonPath('data.0.codes.0.code', 'A');
        $response->assertJsonPath('data.0.codes.0.categories.0.slug', 'general');
        $response->assertJsonPath('data.0.codes.1.code', 'B');
        $response->assertJsonPath('data.0.codes.1.categories.0.slug', 'elderly');
        $response->assertJsonPath('data.0.codes.1.categories.1.slug', 'disability');
        $response->assertJsonPath('data.0.codes.2.code', 'C');
        $response->assertJsonPath('data.0.codes.2.categories.0.slug', 'pregnancy');
    }

    public function test_show_returns_the_section_resolved_by_slug(): void
    {
        $this->seedMatrix();

        $response = $this->getJson('/api/v1/sections/odeco');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'odeco');
        $response->assertJsonPath('data.name', 'ODECO');
        $response->assertJsonCount(3, 'data.codes');
    }

    public function test_show_returns_404_for_an_inactive_section(): void
    {
        $this->seedMatrix();

        Section::query()->where('slug', 'odeco')->sole()->update(['is_active' => false]);

        $this->getJson('/api/v1/sections/odeco')->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->seedMatrix();

        $this->getJson('/api/v1/sections/inexistente')->assertNotFound();
    }

    private function seedMatrix(): void
    {
        $this->seed([CategorySeeder::class, SectionSeeder::class]);
    }
}
