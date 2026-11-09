<?php

namespace Tests\Feature\Auth;

use App\Domain\Users\UserRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-auth')]
class AuthenticationTest extends TestCase
{
    use SeedsTicketMatrix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_can_see_the_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_an_active_operator_can_log_in_and_reach_the_console(): void
    {
        $user = User::factory()->create([
            'email' => 'operadora@example.test',
            'password' => 'secret-password',
        ]);

        $this->post('/login', [
            'email' => 'operadora@example.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('console'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_admin_is_redirected_to_the_admin_panel(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.settings'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'operadora@example.test',
            'password' => 'secret-password',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'operadora@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactiva@example.test',
            'password' => 'secret-password',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'inactiva@example.test',
                'password' => 'secret-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_the_me_endpoint_returns_the_authenticated_user(): void
    {
        $user = User::factory()->supervisor()->create(['name' => 'Supervisora']);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Supervisora')
            ->assertJsonPath('data.role', UserRole::Supervisor->value)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_the_me_endpoint_returns_the_default_section_and_station(): void
    {
        $this->seedMatrix();

        $section = $this->section('plataforma');
        $station = $section->stations()->where('is_active', true)->firstOrFail();

        $user = User::factory()->create([
            'default_section_id' => $section->id,
            'default_station_id' => $station->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.default_section.slug', 'plataforma')
            ->assertJsonPath('data.default_station.id', $station->id)
            ->assertJsonPath('data.default_station.name', $station->name);
    }

    public function test_an_inactive_user_is_blocked_from_the_api(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_the_api_logout_endpoint_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada.');

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_api_routes_require_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/settings')
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/v1/admin/settings')
            ->assertOk();
    }

    public function test_console_api_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/console/queue?section=plataforma')
            ->assertUnauthorized();
    }

    public function test_web_console_and_admin_routes_are_protected(): void
    {
        $operator = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->get('/consola')->assertRedirect(route('login'));
        $this->get('/admin/ajustes')->assertRedirect(route('login'));

        $this->actingAs($operator)->get('/consola')->assertOk();
        $this->actingAs($operator)->get('/admin/ajustes')->assertForbidden();

        $this->actingAs($admin)->get('/admin/ajustes')->assertOk();
    }

    public function test_kiosk_and_display_are_public(): void
    {
        $this->get('/quiosco')->assertOk();
        $this->get('/pantalla')->assertOk();
    }
}
