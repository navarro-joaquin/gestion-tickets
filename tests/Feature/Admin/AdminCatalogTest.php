<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\AuditLog;
use App\Domain\Kiosks\KioskDevice;
use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\StationAssignment;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-admin')]
class AdminCatalogTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_admin_can_list_and_update_sections_with_audit(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/sections')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(3, 'data.0.codes');

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/sections/plataforma', [
                'name' => 'Plataforma Renombrada',
                'is_active' => true,
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Plataforma Renombrada')
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('sections', [
            'slug' => 'plataforma',
            'name' => 'Plataforma Renombrada',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'section.updated',
            'user_id' => $admin->id,
        ]);

        $log = AuditLog::query()->where('action', 'section.updated')->sole();
        $this->assertSame('Plataforma de Atención al Cliente', $log->before['name']);
        $this->assertSame('Plataforma Renombrada', $log->after['name']);
    }

    public function test_a_section_with_active_tickets_cannot_be_deactivated(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        app(IssueTicket::class)->handle('plataforma', 'general', null, Carbon::parse('2026-11-09 08:00:00'));

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/sections/plataforma', [
                'name' => 'Plataforma',
                'is_active' => false,
                'sort_order' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_admin_can_update_the_matrix_and_it_is_audited(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');

        $this->swapGeneralAndPregnancy($section);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/sections/plataforma/matrix', [
                'codes' => [
                    ['code' => 'A', 'categories' => ['general']],
                    ['code' => 'B', 'categories' => ['elderly', 'disability']],
                    ['code' => 'C', 'categories' => ['pregnancy']],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.matrix_confirmed_by', $admin->name);

        $this->assertSame(['general'], $this->categoriesFor($section, 'A'));
        $this->assertSame(['pregnancy'], $this->categoriesFor($section, 'C'));
        $this->assertSame(['disability', 'elderly'], $this->categoriesFor($section, 'B'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'section.matrix.updated',
            'entity_id' => $section->id,
        ]);
    }

    public function test_an_invalid_matrix_is_rejected_and_rolled_back(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');
        $before = $this->matrixSnapshot($section);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/sections/plataforma/matrix', [
                'codes' => [
                    ['code' => 'A', 'categories' => ['general']],
                    ['code' => 'B', 'categories' => ['elderly']],
                    ['code' => 'C', 'categories' => ['disability']],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codes']);

        $this->assertSame($before, $this->matrixSnapshot($section->fresh()));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'section.matrix.updated']);
    }

    public function test_matrix_editing_is_blocked_while_there_are_active_tickets(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        app(IssueTicket::class)->handle('plataforma', 'general', null, Carbon::parse('2026-11-09 08:00:00'));

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/sections/plataforma/matrix', [
                'codes' => [
                    ['code' => 'A', 'categories' => ['general']],
                    ['code' => 'B', 'categories' => ['elderly', 'disability']],
                    ['code' => 'C', 'categories' => ['pregnancy']],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codes']);
    }

    public function test_admin_manages_stations_with_audit(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/stations', [
                'section' => 'plataforma',
                'name' => 'Ventanilla 9',
                'is_active' => true,
                'sort_order' => 9,
            ])
            ->assertCreated()
            ->assertJsonPath('data.section.slug', 'plataforma')
            ->json('data');

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/stations/{$created['id']}", [
                'name' => 'Ventanilla 10',
                'is_active' => true,
                'sort_order' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ventanilla 10');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/stations/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Ventanilla eliminada.');

        $this->assertDatabaseMissing('stations', ['id' => $created['id']]);

        foreach (['station.created', 'station.updated', 'station.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_a_station_with_active_assignment_cannot_be_deactivated(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $station = $this->section('plataforma')->stations()->firstOrFail();

        StationAssignment::factory()->create(['station_id' => $station->id]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/stations/{$station->id}", [
                'name' => $station->name,
                'is_active' => false,
                'sort_order' => $station->sort_order,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_a_station_with_history_is_deactivated_instead_of_deleted(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $station = $this->section('plataforma')->stations()->firstOrFail();

        StationAssignment::factory()->create([
            'station_id' => $station->id,
            'released_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/stations/{$station->id}")
            ->assertOk()
            ->assertJsonPath('message', 'La ventanilla tiene historial: se desactivó en lugar de eliminarse.');

        $this->assertDatabaseHas('stations', ['id' => $station->id, 'is_active' => false]);
    }

    public function test_admin_manages_users_and_protects_the_last_admin(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');
        $station = $section->stations()->where('is_active', true)->firstOrFail();
        $otherSection = $this->section('odeco');
        $otherStation = $otherSection->stations()->where('is_active', true)->firstOrFail();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Nueva Operadora',
                'email' => 'nueva@example.test',
                'password' => 'secret-password',
                'role' => 'operator',
                'is_active' => true,
                'default_section' => $section->slug,
                'default_station_id' => $station->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'operator')
            ->assertJsonPath('data.default_section.slug', $section->slug)
            ->assertJsonPath('data.default_station.id', $station->id)
            ->json('data');

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$created['id']}", [
                'name' => 'Nueva Supervisora',
                'email' => 'nueva@example.test',
                'role' => 'supervisor',
                'is_active' => true,
                'default_section' => $otherSection->slug,
                'default_station_id' => $otherStation->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nueva Supervisora')
            ->assertJsonPath('data.role', 'supervisor')
            ->assertJsonPath('data.default_section.slug', $otherSection->slug)
            ->assertJsonPath('data.default_station.id', $otherStation->id);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'operator',
                'is_active' => true,
                'default_section' => $section->slug,
                'default_station_id' => $station->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$admin->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated']);
    }

    public function test_operator_requires_a_default_section_and_station(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Sin Asignación',
                'email' => 'sin.asignacion@example.test',
                'password' => 'secret-password',
                'role' => 'operator',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_section', 'default_station_id']);

        $this->assertDatabaseMissing('users', ['email' => 'sin.asignacion@example.test']);
    }

    public function test_operator_default_station_must_belong_to_the_default_section(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $station = $this->section('odeco')->stations()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Asignación Cruzada',
                'email' => 'cruzada@example.test',
                'password' => 'secret-password',
                'role' => 'operator',
                'is_active' => true,
                'default_section' => 'plataforma',
                'default_station_id' => $station->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_station_id']);
    }

    public function test_admin_does_not_require_a_default_assignment(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Administradora',
                'email' => 'admin.temporal@example.test',
                'password' => 'secret-password',
                'role' => 'admin',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.default_section', null)
            ->assertJsonPath('data.default_station', null);
    }

    public function test_a_user_with_history_is_deactivated_instead_of_deleted(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();

        StationAssignment::factory()->create([
            'operator_session_id' => OperatorSession::factory()->create([
                'operator_id' => $operator->id,
                'section_id' => $this->section('plataforma')->id,
            ])->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$operator->id}")
            ->assertOk()
            ->assertJsonPath('message', 'El usuario tiene historial: se desactivó en lugar de eliminarse.');

        $this->assertDatabaseHas('users', ['id' => $operator->id, 'is_active' => false]);
    }

    public function test_admin_manages_kiosk_devices_and_the_public_endpoint_filters_sections(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/kiosks', [
                'name' => 'Quiosco Plataforma',
                'is_active' => true,
                'sections' => ['plataforma', 'odeco'],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.sections')
            ->json('data');

        $this->assertSame(48, strlen($created['token']));

        $this->getJson("/api/v1/kiosks/{$created['token']}")
            ->assertOk()
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.slug', 'plataforma');

        $this->assertNotNull(KioskDevice::query()->find($created['id'])->last_seen_at);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/kiosks/{$created['id']}", [
                'name' => 'Quiosco Cajas',
                'is_active' => true,
                'sections' => ['cajas'],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.slug', 'cajas');

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/kiosks/{$created['id']}", [
                'name' => 'Quiosco Cajas',
                'is_active' => false,
                'sections' => ['cajas'],
            ])
            ->assertOk();

        $this->getJson("/api/v1/kiosks/{$created['token']}")->assertNotFound();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/kiosks/{$created['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('kiosk_devices', ['id' => $created['id']]);
    }

    public function test_admin_manages_printers_and_the_console_reads_them(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();
        $station = $this->section('plataforma')->stations()->firstOrFail();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/printers', [
                'station_id' => $station->id,
                'name' => 'EPSON TM-T20II',
                'driver' => 'qz',
                'target' => 'EPSON TM-T20II',
                'paper_width' => 80,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.station.id', $station->id)
            ->json('data');

        $this->actingAs($operator)
            ->getJson("/api/v1/console/printers?station_id={$station->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.driver', 'qz');

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/printers/{$created['id']}", [
                'station_id' => $station->id,
                'name' => 'EPSON TM-T20II',
                'driver' => 'local',
                'target' => 'http://127.0.0.1:9100',
                'paper_width' => 58,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.driver', 'local')
            ->assertJsonPath('data.paper_width', 58);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/printers/{$created['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('printers', ['id' => $created['id']]);
    }

    public function test_audit_logs_can_be_filtered_and_paginated(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/sections/plataforma', [
            'name' => 'Plataforma Auditada',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/audit-logs?action=section.updated&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'section.updated')
            ->assertJsonPath('data.0.user.id', $admin->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_non_admins_cannot_access_the_admin_catalog(): void
    {
        $this->seedMatrix();
        $operator = User::factory()->create();

        foreach ([
            '/api/v1/admin/sections',
            '/api/v1/admin/stations',
            '/api/v1/admin/users',
            '/api/v1/admin/kiosks',
            '/api/v1/admin/printers',
            '/api/v1/admin/audit-logs',
        ] as $path) {
            $this->actingAs($operator)->getJson($path)->assertForbidden();
        }
    }

    private function swapGeneralAndPregnancy(Section $section): void
    {
        $codes = $section->ticketCodes()->with('categories')->get()->keyBy('code');
        $general = Category::query()->where('slug', 'general')->sole()->id;
        $pregnancy = Category::query()->where('slug', 'pregnancy')->sole()->id;

        $codes['A']->categories()->sync([$pregnancy]);
        $codes['C']->categories()->sync([$general]);
    }

    /**
     * @return list<string>
     */
    private function categoriesFor(Section $section, string $code): array
    {
        $ticketCode = SectionTicketCode::query()
            ->where('section_id', $section->id)
            ->where('code', $code)
            ->sole();

        return $ticketCode->categories()
            ->pluck('slug')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function matrixSnapshot(Section $section): array
    {
        $section->load('ticketCodes.categories');

        return $section->ticketCodes
            ->mapWithKeys(fn (SectionTicketCode $code): array => [
                $code->code => $code->categories->pluck('slug')->sort()->values()->all(),
            ])
            ->all();
    }
}
