<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\ReportExport;
use App\Domain\Reports\ReportExportStatus;
use App\Domain\Reports\RetentionPolicy;
use App\Domain\Reports\TicketCsvWriter;
use App\Domain\Reports\TicketReport;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Jobs\GenerateReportExport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-rep')]
class ReportsTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_summary_groups_by_section_category_and_station(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();
        $section = $this->section('plataforma');
        $station = $section->stations()->firstOrFail();
        $other = $this->section('odeco');

        $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 08:00:00'),
            'called_at' => Carbon::parse('2026-11-05 08:05:00'),
            'started_at' => Carbon::parse('2026-11-05 08:10:00'),
            'finished_at' => Carbon::parse('2026-11-05 08:20:00'),
            'closed_at' => Carbon::parse('2026-11-05 08:20:00'),
            'station_label' => $station->name,
            'operator_id' => $operator->id,
        ]);

        $this->ticket($section, 'C', 'pregnancy', [
            'status' => TicketStatus::NoShow,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 09:00:00'),
            'called_at' => Carbon::parse('2026-11-05 09:04:00'),
            'call_count' => 2,
            'closed_at' => Carbon::parse('2026-11-05 09:10:00'),
            'station_label' => $station->name,
        ]);

        $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Expired,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 10:00:00'),
            'closed_at' => Carbon::parse('2026-11-05 16:30:00'),
        ]);

        $this->ticket($other, 'D', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 11:00:00'),
            'closed_at' => Carbon::parse('2026-11-05 11:30:00'),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/summary?from=2026-11-05&to=2026-11-05&section=plataforma')
            ->assertOk()
            ->assertJsonPath('data.totals.issued', 3)
            ->assertJsonPath('data.totals.finished', 1)
            ->assertJsonPath('data.totals.no_show', 1)
            ->assertJsonPath('data.totals.expired', 1)
            ->assertJsonPath('data.totals.cancelled', 0)
            ->assertJsonPath('data.totals.avg_wait_seconds', 270)
            ->assertJsonPath('data.totals.avg_service_seconds', 600)
            ->assertJsonPath('data.totals.avg_total_seconds', 8400)
            ->assertJsonCount(1, 'data.by_section')
            ->assertJsonPath('data.by_section.0.section.slug', 'plataforma')
            ->assertJsonPath('data.by_section.0.issued', 3)
            ->assertJsonCount(2, 'data.by_category')
            ->assertJsonCount(2, 'data.by_station');

        $stations = collect($response->json('data.by_station'))
            ->keyBy(fn (array $row): string => $row['station_label'] ?? 'sin-ventanilla');

        $this->assertSame(2, $stations[$station->name]['issued']);
        $this->assertSame(1, $stations['sin-ventanilla']['expired']);

        $categories = collect($response->json('data.by_category'))
            ->keyBy(fn (array $row): string => $row['category']['slug']);

        $this->assertSame(2, $categories['general']['issued']);
        $this->assertSame(1, $categories['general']['expired']);
        $this->assertSame(1, $categories['pregnancy']['no_show']);
    }

    public function test_summary_filters_by_category_service_and_station(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');
        $station = $section->stations()->firstOrFail();
        $service = Service::factory()->create([
            'section_id' => $section->id,
            'slug' => 'certificados',
            'name' => 'Certificados',
            'label' => 'CERT',
        ]);

        $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 08:00:00'),
            'closed_at' => Carbon::parse('2026-11-05 08:15:00'),
            'station_label' => $station->name,
        ]);

        $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 09:00:00'),
            'closed_at' => Carbon::parse('2026-11-05 09:15:00'),
            'station_label' => 'Ventanilla 2',
            'service_id' => $service->id,
            'service_label' => $service->label,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/summary?from=2026-11-05&to=2026-11-05&service=certificados')
            ->assertOk()
            ->assertJsonPath('data.totals.issued', 1)
            ->assertJsonCount(1, 'data.by_service')
            ->assertJsonPath('data.by_service.0.service.slug', 'certificados')
            ->assertJsonPath('data.by_service.0.service.label', 'CERT');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/summary?from=2026-11-05&to=2026-11-05&station=Ventanilla%202')
            ->assertOk()
            ->assertJsonPath('data.totals.issued', 1);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/summary?from=2026-11-05&to=2026-11-05&category=general')
            ->assertOk()
            ->assertJsonPath('data.totals.issued', 2);
    }

    public function test_ticket_detail_is_paginated_and_filtered(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');

        foreach (['general', 'general', 'pregnancy'] as $index => $category) {
            $code = $category === 'pregnancy' ? 'C' : 'A';

            $this->ticket($section, $code, $category, [
                'status' => TicketStatus::Finished,
                'period_key' => '2026-11-05',
                'issued_at' => Carbon::parse(sprintf('2026-11-05 %02d:00:00', $index + 8)),
                'closed_at' => Carbon::parse(sprintf('2026-11-05 %02d:30:00', $index + 8)),
            ]);
        }

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/tickets?from=2026-11-05&to=2026-11-05&category=general&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.category.slug', 'general')
            ->assertJsonPath('data.0.status', 'finished');
    }

    public function test_export_is_queued_and_the_job_generates_a_csv(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->seedMatrix();

        $admin = User::factory()->admin()->create();
        $section = $this->section('plataforma');

        $ticket = $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-11-05',
            'issued_at' => Carbon::parse('2026-11-05 08:00:00'),
            'called_at' => Carbon::parse('2026-11-05 08:05:00'),
            'started_at' => Carbon::parse('2026-11-05 08:10:00'),
            'finished_at' => Carbon::parse('2026-11-05 08:20:00'),
            'closed_at' => Carbon::parse('2026-11-05 08:20:00'),
        ]);

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/reports/exports', [
                'from' => '2026-11-05',
                'to' => '2026-11-05',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->json('data');

        Queue::assertPushed(GenerateReportExport::class);

        $export = ReportExport::query()->findOrFail($created['id']);

        (new GenerateReportExport($export))->handle(
            app(TicketReport::class),
            app(TicketCsvWriter::class),
        );

        $export->refresh();

        $this->assertSame(ReportExportStatus::Completed, $export->status);
        $this->assertSame(1, $export->rows);
        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);

        $contents = Storage::disk('local')->get($export->file_path);

        $this->assertStringContainsString('Ticket', $contents);
        $this->assertStringContainsString($ticket->label, $contents);
        $this->assertStringContainsString('Finalizado', $contents);
    }

    public function test_export_download_and_delete(): void
    {
        Storage::fake('local');
        $this->seedMatrix();

        $admin = User::factory()->admin()->create();
        $export = ReportExport::factory()->create([
            'user_id' => $admin->id,
            'status' => ReportExportStatus::Completed,
            'file_path' => 'reports/demo.csv',
            'rows' => 1,
            'file_size' => 10,
        ]);

        Storage::disk('local')->put('reports/demo.csv', "ID,Ticket\n1,A-001\n");

        $this->actingAs($admin)
            ->get('/api/v1/admin/reports/exports/'.$export->id.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/reports/exports/'.$export->id)
            ->assertOk()
            ->assertJsonPath('message', 'Exportación eliminada.');

        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing('reports/demo.csv');
    }

    public function test_retention_months_can_be_configured_from_admin_settings(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/settings', [
                'closing_time' => '17:15',
                'retention_months' => 18,
            ])
            ->assertOk()
            ->assertJsonPath('data.retention_months', 18);

        $this->assertSame(18, app(RetentionPolicy::class)->months());
    }

    public function test_purge_archives_and_deletes_old_terminal_tickets(): void
    {
        Storage::fake('local');
        $this->seedMatrix();

        $section = $this->section('plataforma');

        $old = $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2025-09-01',
            'issued_at' => Carbon::parse('2025-09-01 08:00:00'),
            'closed_at' => Carbon::parse('2025-09-01 08:30:00'),
        ]);

        $recent = $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Finished,
            'period_key' => '2026-10-01',
            'issued_at' => Carbon::parse('2026-10-01 08:00:00'),
            'closed_at' => Carbon::parse('2026-10-01 08:30:00'),
        ]);

        $waiting = $this->ticket($section, 'A', 'general', [
            'status' => TicketStatus::Waiting,
            'period_key' => '2025-08-01',
            'issued_at' => Carbon::parse('2025-08-01 08:00:00'),
        ]);

        $this->artisan('tickets:purge --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('tickets', ['id' => $old->id]);

        $this->artisan('tickets:purge')->assertSuccessful();

        $this->assertDatabaseMissing('tickets', ['id' => $old->id]);
        $this->assertDatabaseHas('tickets', ['id' => $recent->id]);
        $this->assertDatabaseHas('tickets', ['id' => $waiting->id]);

        $files = Storage::disk('local')->files('archives');

        $this->assertCount(1, $files);
        $this->assertStringContainsString($old->label, Storage::disk('local')->get($files[0]));
        $this->assertStringNotContainsString($recent->label, Storage::disk('local')->get($files[0]));
    }

    public function test_non_admins_cannot_access_reports_or_exports(): void
    {
        $this->seedMatrix();
        $operator = User::factory()->create();

        foreach ([
            '/api/v1/admin/reports/summary',
            '/api/v1/admin/reports/tickets',
            '/api/v1/admin/reports/exports',
        ] as $path) {
            $this->actingAs($operator)->getJson($path)->assertForbidden();
        }

        $this->actingAs($operator)
            ->postJson('/api/v1/admin/reports/exports', ['from' => '2026-11-05', 'to' => '2026-11-05'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(
        Section $section,
        string $code,
        string $categorySlug,
        array $attributes,
    ): Ticket {
        $ticketCode = $section->ticketCodes()->where('code', $code)->firstOrFail();
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        return Ticket::factory()
            ->forMatrix($section, $ticketCode, $category)
            ->create($attributes);
    }
}
