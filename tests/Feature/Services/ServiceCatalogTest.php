<?php

namespace Tests\Feature\Services;

use App\Domain\Services\Service;
use App\Domain\Ticketing\Actions\CallNextTicket;
use App\Domain\Ticketing\Actions\FinishTicket;
use App\Domain\Ticketing\Actions\IssueTicket;
use App\Domain\Ticketing\Actions\StartTicket;
use App\Domain\Ticketing\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-svc')]
class ServiceCatalogTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_a_service_ticket_uses_the_general_code_and_its_label(): void
    {
        $this->seedMatrix();
        $moment = Carbon::parse('2026-11-09 09:00:00');

        $service = $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
        ]);

        $ticket = app(IssueTicket::class)->handle('plataforma', null, 'visa', $moment);

        $this->assertSame('A', $ticket->code);
        $this->assertSame(1, $ticket->number);
        $this->assertSame('A-001 · VIS', $ticket->label);
        $this->assertSame('VIS', $ticket->service_label);
        $this->assertSame($service->id, $ticket->service_id);
        $this->assertSame('general', $ticket->category->slug);
        $this->assertFalse($ticket->is_preferential);
    }

    public function test_a_service_outside_its_day_or_time_window_is_rejected(): void
    {
        $this->seedMatrix();
        $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
            'available_days' => [1],
            'available_from' => '08:00',
            'available_to' => '12:00',
        ]);

        app(IssueTicket::class)->handle(
            'plataforma',
            null,
            'visa',
            Carbon::parse('2026-11-09 09:00:00'),
        );

        $this->assertServiceRejected(Carbon::parse('2026-11-10 09:00:00'));
        $this->assertServiceRejected(Carbon::parse('2026-11-09 13:00:00'));
    }

    public function test_a_service_outside_its_effective_dates_is_rejected(): void
    {
        $this->seedMatrix();
        $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
            'effective_from' => '2026-11-10',
            'effective_to' => '2026-11-20',
        ]);

        $this->assertServiceRejected(Carbon::parse('2026-11-09 09:00:00'));
        $this->assertServiceRejected(Carbon::parse('2026-11-21 09:00:00'));

        app(IssueTicket::class)->handle(
            'plataforma',
            null,
            'visa',
            Carbon::parse('2026-11-15 09:00:00'),
        );
    }

    public function test_an_operator_without_authorization_does_not_receive_service_tickets(): void
    {
        $this->seedMatrix();
        $moment = Carbon::parse('2026-11-09 09:00:00');

        $service = $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
        ]);

        $serviceTicket = app(IssueTicket::class)->handle('plataforma', null, 'visa', $moment);
        $generalTicket = app(IssueTicket::class)->handle('plataforma', 'general', null, $moment);

        $operator = User::factory()->create();

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', $operator->id, $moment);
        $this->assertNotNull($called);
        $this->assertSame($generalTicket->id, $called->id);

        $this->finish($called, $moment);

        $service->operators()->attach($operator->id);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', $operator->id, $moment);
        $this->assertNotNull($called);
        $this->assertSame($serviceTicket->id, $called->id);
    }

    public function test_an_operator_only_receives_services_they_are_authorized_for(): void
    {
        $this->seedMatrix();
        $moment = Carbon::parse('2026-11-09 09:00:00');

        $authorized = $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
            'sort_order' => 1,
        ]);

        $this->createService([
            'slug' => 'pasaporte',
            'name' => 'Trámite de pasaporte',
            'label' => 'PAS',
            'sort_order' => 2,
        ]);

        app(IssueTicket::class)->handle('plataforma', null, 'visa', $moment);
        $other = app(IssueTicket::class)->handle('plataforma', null, 'pasaporte', $moment);

        $operator = User::factory()->create();
        $authorized->operators()->attach($operator->id);

        $called = app(CallNextTicket::class)->handle('plataforma', 'Ventanilla 1', $operator->id, $moment);

        $this->assertNotNull($called);
        $this->assertSame($authorized->id, $called->service_id);
        $this->assertNotSame($other->id, $called->id);
    }

    public function test_the_section_endpoint_lists_only_available_services(): void
    {
        $this->seedMatrix();

        $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
        ]);

        $this->createService([
            'slug' => 'pasaporte',
            'name' => 'Trámite de pasaporte',
            'label' => 'PAS',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/sections/plataforma')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.slug', 'visa')
            ->assertJsonPath('data.services.0.label', 'VIS');
    }

    public function test_admin_can_create_update_and_delete_a_service(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/services', [
                'section' => 'plataforma',
                'slug' => 'visa',
                'name' => 'Trámite de visa',
                'label' => 'VIS',
                'is_active' => true,
                'available_days' => [1, 2, 3],
                'available_from' => '08:00',
                'available_to' => '12:00',
                'operator_ids' => [$operator->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'visa')
            ->assertJsonPath('data.section.slug', 'plataforma')
            ->assertJsonPath('data.operators.0.id', $operator->id)
            ->json('data');

        $this->assertDatabaseHas('service_user', [
            'service_id' => $created['id'],
            'user_id' => $operator->id,
        ]);

        $this->putJson("/api/v1/admin/services/{$created['id']}", [
            'section' => 'plataforma',
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VISA',
            'is_active' => true,
            'operator_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'VISA')
            ->assertJsonPath('data.operators', []);

        $this->assertDatabaseMissing('service_user', [
            'service_id' => $created['id'],
            'user_id' => $operator->id,
        ]);

        $this->deleteJson("/api/v1/admin/services/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Servicio eliminado.');

        $this->assertDatabaseMissing('services', ['id' => $created['id']]);
    }

    public function test_a_service_slug_is_unique_within_its_section(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->createService(['slug' => 'visa', 'name' => 'Trámite de visa', 'label' => 'VIS']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/services', [
                'section' => 'plataforma',
                'slug' => 'visa',
                'name' => 'Otro trámite',
                'label' => 'OTR',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_only_admins_can_manage_services(): void
    {
        $this->seedMatrix();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/services', [
                'section' => 'plataforma',
                'slug' => 'visa',
                'name' => 'Trámite de visa',
                'label' => 'VIS',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/services')
            ->assertForbidden();
    }

    public function test_deleting_a_service_with_history_deactivates_it_instead(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $service = $this->createService([
            'slug' => 'visa',
            'name' => 'Trámite de visa',
            'label' => 'VIS',
        ]);

        app(IssueTicket::class)->handle(
            'plataforma',
            null,
            'visa',
            Carbon::parse('2026-11-09 09:00:00'),
        );

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('message', 'El servicio tiene historial: se desactivó en lugar de eliminarse.');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'is_active' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createService(array $attributes): Service
    {
        return Service::factory()->create(array_merge([
            'section_id' => $this->section('plataforma')->id,
        ], $attributes));
    }

    private function assertServiceRejected(Carbon $moment): void
    {
        try {
            app(IssueTicket::class)->handle('plataforma', null, 'visa', $moment);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service', $exception->errors());
        }
    }

    private function finish(Ticket $ticket, Carbon $moment): void
    {
        $started = app(StartTicket::class)->handle($ticket, $moment);

        app(FinishTicket::class)->handle($started, $moment);
    }
}
