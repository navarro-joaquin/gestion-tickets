<?php

namespace Tests\Feature\Sections;

use App\Domain\Sections\Section;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SectionSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('t-map')]
class VerifyTicketCodesCommandTest extends TestCase
{
    public function test_command_fails_when_no_sections_exist(): void
    {
        $this->artisan('tickets:codes:verify')
            ->expectsOutputToContain('No hay secciones configuradas')
            ->assertExitCode(1);
    }

    public function test_command_succeeds_with_the_confirmed_seeded_matrix(): void
    {
        $this->seedMatrix();

        $this->artisan('tickets:codes:verify')->assertExitCode(0);
    }

    public function test_strict_mode_fails_when_a_section_is_inactive(): void
    {
        $this->seedMatrix();

        $this->deactivateSection('odeco');

        $this->artisan('tickets:codes:verify')->assertExitCode(0);
        $this->artisan('tickets:codes:verify', ['--strict' => true])->assertExitCode(1);
    }

    public function test_strict_mode_fails_when_a_section_has_no_confirmed_matrix(): void
    {
        $this->seedMatrix();

        Section::query()->where('slug', 'cajas')->sole()->update(['matrix_confirmed_at' => null]);

        $this->artisan('tickets:codes:verify')->assertExitCode(0);
        $this->artisan('tickets:codes:verify', ['--strict' => true])->assertExitCode(1);
    }

    public function test_command_fails_without_strict_when_a_matrix_is_structurally_invalid(): void
    {
        $this->seedMatrix();

        Section::query()
            ->where('slug', 'plataforma')
            ->sole()
            ->ticketCodes()
            ->where('code', 'B')
            ->sole()
            ->update(['is_active' => false]);

        $this->artisan('tickets:codes:verify')
            ->expectsOutputToContain('La verificación encontró secciones con problemas')
            ->assertExitCode(1);
    }

    private function seedMatrix(): void
    {
        $this->seed([CategorySeeder::class, SectionSeeder::class]);
    }

    private function deactivateSection(string $slug): void
    {
        Section::query()->where('slug', $slug)->sole()->update(['is_active' => false]);
    }
}
