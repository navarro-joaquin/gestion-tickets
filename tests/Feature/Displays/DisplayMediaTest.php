<?php

namespace Tests\Feature\Displays;

use App\Domain\Displays\Display;
use App\Domain\Media\MediaAsset;
use App\Domain\Media\Playlist;
use App\Domain\Media\PlaylistItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\TestCase;

#[Group('t-media')]
class DisplayMediaTest extends TestCase
{
    use SeedsTicketMatrix;

    public function test_admin_can_upload_and_list_media_assets(): void
    {
        Storage::fake('public');
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post('/api/v1/admin/media', [
                'name' => 'Promocional de octubre',
                'type' => 'image',
                'duration_seconds' => 15,
                'is_active' => true,
                'file' => UploadedFile::fake()->image('promocional.jpg', 800, 600),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Promocional de octubre')
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.duration_seconds', 15)
            ->assertJsonPath('data.is_active', true);

        $asset = MediaAsset::query()->sole();

        $this->assertStringContainsString('/storage/media/', $response->json('data.url'));
        Storage::disk('public')->assertExists($asset->path);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/media')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $asset->id);
    }

    public function test_media_upload_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/api/v1/admin/media', [
                'name' => 'Documento',
                'type' => 'image',
                'file' => UploadedFile::fake()->create('documento.txt', 10, 'text/plain'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_media_upload_rejects_a_file_that_does_not_match_the_declared_type(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/api/v1/admin/media', [
                'name' => 'Imagen declarada como video',
                'type' => 'video',
                'file' => UploadedFile::fake()->image('promocional.jpg', 640, 480),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media_assets', 0);

        $this->actingAs($admin)
            ->post('/api/v1/admin/media', [
                'name' => 'Video declarado como imagen',
                'type' => 'image',
                'file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_only_admins_can_manage_media_playlists_and_displays(): void
    {
        $operator = User::factory()->create();

        foreach ([
            '/api/v1/admin/media',
            '/api/v1/admin/playlists',
            '/api/v1/admin/displays',
        ] as $path) {
            $this->actingAs($operator)->getJson($path)->assertForbidden();
        }

        $this->actingAs($operator)
            ->postJson('/api/v1/admin/playlists', [
                'name' => 'No permitida',
                'items' => [],
            ])
            ->assertForbidden();
    }

    public function test_playlist_items_keep_the_requested_order(): void
    {
        Storage::fake('public');
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $first = MediaAsset::factory()->create(['name' => 'Primero']);
        $second = MediaAsset::factory()->create(['name' => 'Segundo']);

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/playlists', [
                'name' => 'Playlist de sala',
                'description' => 'Contenido general',
                'is_active' => true,
                'items' => [
                    ['media_asset_id' => $second->id, 'duration_seconds' => 12],
                    ['media_asset_id' => $first->id, 'duration_seconds' => 8],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.media.name', 'Segundo')
            ->assertJsonPath('data.items.0.position', 0)
            ->assertJsonPath('data.items.1.media.name', 'Primero')
            ->json('data');

        $playlist = Playlist::query()->findOrFail($created['id']);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/playlists/'.$playlist->id, [
                'name' => 'Playlist de sala',
                'description' => 'Contenido general',
                'is_active' => true,
                'items' => [
                    ['media_asset_id' => $first->id, 'duration_seconds' => 8],
                    ['media_asset_id' => $second->id, 'duration_seconds' => 12],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.media.name', 'Primero')
            ->assertJsonPath('data.items.1.media.name', 'Segundo');

        $this->assertSame(
            [$first->id, $second->id],
            $playlist->items()->pluck('media_asset_id')->all(),
        );
    }

    public function test_display_is_created_with_sections_and_playlists_and_rotates_its_token(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();
        $playlist = Playlist::factory()->create();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/displays', [
                'name' => 'Pantalla principal',
                'layout' => 'mixed',
                'is_active' => true,
                'sections' => ['plataforma', 'odeco'],
                'playlists' => [$playlist->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pantalla principal')
            ->assertJsonPath('data.layout', 'mixed')
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonCount(1, 'data.playlists')
            ->json('data');

        $this->assertSame(48, mb_strlen($created['token']));
        $this->assertStringContainsString('/pantalla?pantalla=', $created['url']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/displays/'.$created['id'].'/token')
            ->assertOk()
            ->assertJsonPath('data.id', $created['id']);

        $this->assertNotSame($created['token'], Display::query()->findOrFail($created['id'])->token);
    }

    public function test_public_snapshot_filters_sections_and_returns_playlist_media(): void
    {
        Storage::fake('public');
        $this->seedMatrix();
        $asset = MediaAsset::factory()->create(['name' => 'Bienvenida']);
        $playlist = Playlist::factory()->create(['name' => 'Sala de espera']);
        PlaylistItem::factory()->create([
            'playlist_id' => $playlist->id,
            'media_asset_id' => $asset->id,
            'position' => 0,
        ]);
        $display = Display::factory()->create([
            'layout' => Display::LAYOUT_MIXED,
        ]);
        $display->sections()->sync([$this->section('plataforma')->id]);
        $display->playlists()->sync([$playlist->id]);

        $this->getJson('/api/v1/display/snapshot?display='.$display->token)
            ->assertOk()
            ->assertJsonPath('display.layout', 'mixed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.section.slug', 'plataforma')
            ->assertJsonPath('playlists.0.name', 'Sala de espera')
            ->assertJsonPath('playlists.0.items.0.media.name', 'Bienvenida');

        $this->assertNotNull($display->fresh()->last_seen_at);

        $this->getJson('/api/v1/display/snapshot')
            ->assertOk()
            ->assertJsonPath('display', null)
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(0, 'playlists');
    }

    public function test_inactive_or_unknown_display_tokens_return_404(): void
    {
        $this->seedMatrix();
        $inactive = Display::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/display/snapshot?display='.$inactive->token)->assertNotFound();
        $this->getJson('/api/v1/display/snapshot?display=no-existe')->assertNotFound();
    }

    public function test_inactive_media_and_playlists_are_excluded_from_the_snapshot(): void
    {
        Storage::fake('public');
        $this->seedMatrix();
        $active = MediaAsset::factory()->create(['name' => 'Visible']);
        $inactiveAsset = MediaAsset::factory()->create(['name' => 'Oculto', 'is_active' => false]);
        $playlist = Playlist::factory()->create(['name' => 'Activa']);
        $inactivePlaylist = Playlist::factory()->create(['name' => 'Inactiva', 'is_active' => false]);
        PlaylistItem::factory()->create([
            'playlist_id' => $playlist->id,
            'media_asset_id' => $active->id,
            'position' => 0,
        ]);
        PlaylistItem::factory()->create([
            'playlist_id' => $playlist->id,
            'media_asset_id' => $inactiveAsset->id,
            'position' => 1,
        ]);
        $display = Display::factory()->create();
        $display->sections()->sync([$this->section('plataforma')->id]);
        $display->playlists()->sync([$playlist->id, $inactivePlaylist->id]);

        $this->getJson('/api/v1/display/snapshot?display='.$display->token)
            ->assertOk()
            ->assertJsonCount(1, 'playlists')
            ->assertJsonPath('playlists.0.name', 'Activa')
            ->assertJsonCount(1, 'playlists.0.items')
            ->assertJsonPath('playlists.0.items.0.media.name', 'Visible');
    }

    public function test_deleting_media_removes_the_file_and_playlist_items(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $asset = MediaAsset::factory()->create(['path' => 'media/demo.jpg']);
        Storage::disk('public')->put($asset->path, 'contenido');
        $item = PlaylistItem::factory()->create(['media_asset_id' => $asset->id]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/media/'.$asset->id)
            ->assertOk();

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        $this->assertDatabaseMissing('playlist_items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_display_requires_at_least_one_active_section(): void
    {
        $this->seedMatrix();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/displays', [
                'name' => 'Sin secciones',
                'layout' => 'queue',
                'is_active' => true,
                'sections' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sections');
    }
}
