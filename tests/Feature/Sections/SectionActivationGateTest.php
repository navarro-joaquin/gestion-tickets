<?php

namespace Tests\Feature\Sections;

use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SectionSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('t-map')]
class SectionActivationGateTest extends TestCase
{
    public function test_confirmed_and_active_seeded_section_is_operational(): void
    {
        $this->seedMatrix();
        $gate = app(SectionActivationGate::class);

        $this->assertTrue($gate->isOperational($this->seededSection('plataforma')));
    }

    public function test_inactive_section_is_not_operational(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('odeco');
        $section->update(['is_active' => false]);

        $this->assertFalse(app(SectionActivationGate::class)->isOperational($section->fresh()));
    }

    public function test_section_without_confirmed_matrix_is_not_operational(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('cajas');
        $section->update(['matrix_confirmed_at' => null]);

        $this->assertFalse(app(SectionActivationGate::class)->isOperational($section->fresh()));
    }

    public function test_section_with_invalid_matrix_is_not_operational(): void
    {
        $this->seedMatrix();

        $section = $this->seededSection('plataforma');
        $section->ticketCodes()->where('code', 'C')->sole()->update(['is_active' => false]);

        $this->assertFalse(app(SectionActivationGate::class)->isOperational($section->fresh()));
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
