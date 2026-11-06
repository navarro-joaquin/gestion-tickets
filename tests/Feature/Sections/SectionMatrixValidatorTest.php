<?php

namespace Tests\Feature\Sections;

use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionMatrixValidator;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SectionSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('t-map')]
class SectionMatrixValidatorTest extends TestCase
{
    public function test_confirmed_matrix_of_every_seeded_section_is_valid(): void
    {
        $this->seedMatrix();
        $validator = app(SectionMatrixValidator::class);

        $sections = Section::query()->with('ticketCodes.categories')->get();

        $this->assertCount(3, $sections);

        foreach ($sections as $section) {
            $result = $validator->validate($section);

            $this->assertTrue(
                $result->isValid(),
                "La matriz de {$section->slug} no es válida: ".implode(' | ', $result->errors()),
            );
        }
    }

    public function test_section_without_three_active_codes_is_invalid(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('plataforma');
        $section->ticketCodes()->where('code', 'B')->sole()->update(['is_active' => false]);

        $result = app(SectionMatrixValidator::class)->validate($section->fresh());

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('exactamente 3 códigos activos', implode(' | ', $result->errors()));
    }

    public function test_section_with_a_code_that_mixes_general_and_preferential_categories_is_invalid(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('plataforma');
        $pregnancy = Category::query()->where('slug', 'pregnancy')->sole();

        $section->ticketCodes()->where('code', 'A')->sole()->categories()->attach($pregnancy);

        $result = app(SectionMatrixValidator::class)->validate($section->fresh());

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('combinación de categorías no permitida', implode(' | ', $result->errors()));
    }

    public function test_section_with_a_category_assigned_to_two_codes_is_invalid(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('plataforma');
        $general = Category::query()->where('slug', 'general')->sole();

        $section->ticketCodes()->where('code', 'C')->sole()->categories()->sync([$general->id]);

        $result = app(SectionMatrixValidator::class)->validate($section->fresh());
        $errors = implode(' | ', $result->errors());

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('La categoría general', $errors);
        $this->assertStringContainsString('La categoría embarazo', $errors);
    }

    public function test_inactive_codes_are_ignored_when_validating_the_matrix(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('plataforma');
        $general = Category::query()->where('slug', 'general')->sole();

        $extraCode = $section->ticketCodes()->create([
            'code' => 'Z',
            'sort_order' => 4,
            'is_active' => false,
        ]);
        $extraCode->categories()->attach($general);

        $result = app(SectionMatrixValidator::class)->validate($section->fresh());

        $this->assertTrue($result->isValid(), implode(' | ', $result->errors()));
    }

    private function seedMatrix(): void
    {
        $this->seed([CategorySeeder::class, SectionSeeder::class]);
    }

    private function seededSection(string $slug): Section
    {
        return Section::query()->where('slug', $slug)->sole();
    }
}
