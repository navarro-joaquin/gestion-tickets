<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Domain\Ticketing\Ticket;
use App\Domain\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\OperatorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['defaultSection', 'defaultStation'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return AdminUserResource::collection($users);
    }

    public function operators(): JsonResponse
    {
        $operators = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Operator, UserRole::Supervisor])
            ->orderBy('name')
            ->get();

        return OperatorResource::collection($operators)->response();
    }

    public function store(AdminUserRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = User::query()->create(
            $this->withDefaultAssignment($request->validated()),
        );
        $user->load(['defaultSection', 'defaultStation']);

        $audit->record('user.created', $user, null, $audit->userPayload($user), $request);

        return response()->json([
            'data' => new AdminUserResource($user),
            'message' => 'Usuario creado.',
        ], 201);
    }

    public function update(
        AdminUserRequest $request,
        User $user,
        AuditLogger $audit,
    ): JsonResponse {
        $data = $this->withDefaultAssignment($request->validated());

        if ($request->user()?->id === $user->id) {
            if (! $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => ['No puedes desactivar tu propio usuario.'],
                ]);
            }

            if ($data['role'] !== UserRole::Admin->value) {
                throw ValidationException::withMessages([
                    'role' => ['No puedes quitarte el rol de administrador.'],
                ]);
            }
        }

        $before = $audit->userPayload($user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $user->load(['defaultSection', 'defaultStation']);

        $audit->record('user.updated', $user, $before, $audit->userPayload($user), $request);

        return response()->json([
            'data' => new AdminUserResource($user),
            'message' => 'Usuario actualizado.',
        ]);
    }

    public function destroy(
        User $user,
        AuditLogger $audit,
    ): JsonResponse {
        if (request()->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['No puedes eliminar tu propio usuario.'],
            ]);
        }

        if ($this->hasHistory($user)) {
            $before = $audit->userPayload($user);
            $user->update(['is_active' => false]);
            $audit->record('user.deactivated', $user, $before, $audit->userPayload($user));

            return response()->json([
                'message' => 'El usuario tiene historial: se desactivó en lugar de eliminarse.',
            ]);
        }

        $before = $audit->userPayload($user);
        $user->delete();
        $audit->record('user.deleted', $user, $before, null);

        return response()->json([
            'message' => 'Usuario eliminado.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDefaultAssignment(array $data): array
    {
        $sectionSlug = $data['default_section'] ?? null;
        $stationId = $data['default_station_id'] ?? null;

        unset($data['default_section'], $data['default_station_id']);

        $role = UserRole::tryFrom((string) ($data['role'] ?? ''));

        if (! in_array($role, [UserRole::Operator, UserRole::Supervisor], true)) {
            $data['default_section_id'] = null;
            $data['default_station_id'] = null;

            return $data;
        }

        $data['default_section_id'] = Section::query()
            ->where('slug', $sectionSlug)
            ->value('id');
        $data['default_station_id'] = $stationId === null ? null : (int) $stationId;

        return $data;
    }

    private function hasHistory(User $user): bool
    {
        return Ticket::query()->where('operator_id', $user->id)->exists()
            || OperatorSession::query()->where('operator_id', $user->id)->exists()
            || $user->authorizedServices()->exists();
    }
}
