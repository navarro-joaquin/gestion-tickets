<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Kiosks\KioskDevice;
use App\Domain\Sections\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\KioskDeviceRequest;
use App\Http\Resources\KioskDeviceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AdminKioskDeviceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $devices = KioskDevice::query()
            ->with('sections')
            ->orderBy('name')
            ->get();

        return KioskDeviceResource::collection($devices);
    }

    public function store(KioskDeviceRequest $request, AuditLogger $audit): JsonResponse
    {
        $device = KioskDevice::query()->create([
            'name' => $request->validated('name'),
            'slug' => $this->uniqueSlug($request->validated('slug') ?? $request->validated('name')),
            'token' => Str::random(48),
            'is_active' => $request->validated('is_active'),
        ]);

        $device->sections()->sync($this->sectionIds($request->validated('sections')));

        $audit->record('kiosk.created', $device, null, $this->payload($device), $request);

        $device->load('sections');

        return response()->json([
            'data' => new KioskDeviceResource($device),
            'message' => 'Quiosco creado.',
        ], 201);
    }

    public function update(
        KioskDeviceRequest $request,
        KioskDevice $kioskDevice,
        AuditLogger $audit,
    ): JsonResponse {
        $before = $this->payload($kioskDevice);

        $kioskDevice->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') !== null
                ? $this->uniqueSlug($request->validated('slug'), $kioskDevice->id)
                : $kioskDevice->slug,
            'is_active' => $request->validated('is_active'),
        ]);

        $kioskDevice->sections()->sync($this->sectionIds($request->validated('sections')));

        $kioskDevice->load('sections');

        $audit->record('kiosk.updated', $kioskDevice, $before, $this->payload($kioskDevice), $request);

        return response()->json([
            'data' => new KioskDeviceResource($kioskDevice),
            'message' => 'Quiosco actualizado.',
        ]);
    }

    public function destroy(KioskDevice $kioskDevice, AuditLogger $audit): JsonResponse
    {
        $before = $this->payload($kioskDevice);
        $kioskDevice->delete();
        $audit->record('kiosk.deleted', $kioskDevice, $before, null);

        return response()->json([
            'message' => 'Quiosco eliminado.',
        ]);
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function sectionIds(array $slugs): array
    {
        return Section::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'quiosco';
        $slug = $base;
        $suffix = 2;

        while (KioskDevice::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(KioskDevice $device): array
    {
        return [
            'name' => $device->name,
            'slug' => $device->slug,
            'is_active' => $device->is_active,
            'sections' => $device->sections->pluck('slug')->values()->all(),
        ];
    }
}
