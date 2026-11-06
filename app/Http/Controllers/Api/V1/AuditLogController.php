<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'entity_type' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when(
                ! empty($validated['entity_type']),
                fn ($query) => $query->where('entity_type', $validated['entity_type']),
            )
            ->when(
                ! empty($validated['action']),
                fn ($query) => $query->where('action', $validated['action']),
            )
            ->when(
                ! empty($validated['user_id']),
                fn ($query) => $query->where('user_id', $validated['user_id']),
            )
            ->when(
                ! empty($validated['from']),
                fn ($query) => $query->whereDate('created_at', '>=', $validated['from']),
            )
            ->when(
                ! empty($validated['to']),
                fn ($query) => $query->whereDate('created_at', '<=', $validated['to']),
            )
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 50);

        return AuditLogResource::collection($logs);
    }
}
