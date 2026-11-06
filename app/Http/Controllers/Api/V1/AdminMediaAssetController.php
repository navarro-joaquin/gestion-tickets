<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\MediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MediaAssetRequest;
use App\Http\Resources\MediaAssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AdminMediaAssetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $assets = MediaAsset::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return MediaAssetResource::collection($assets);
    }

    public function store(MediaAssetRequest $request, AuditLogger $audit): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('media', 'public');

        $asset = MediaAsset::query()->create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'disk' => 'public',
            'path' => $path,
            'mime' => $file->getMimeType(),
            'file_size' => $file->getSize() ?? 0,
            'duration_seconds' => $request->validated('duration_seconds'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $audit->record('media.created', $asset, null, $this->payload($asset), $request);

        return response()->json([
            'data' => new MediaAssetResource($asset),
            'message' => 'Contenido creado.',
        ], 201);
    }

    public function update(
        MediaAssetRequest $request,
        MediaAsset $mediaAsset,
        AuditLogger $audit,
    ): JsonResponse {
        $before = $this->payload($mediaAsset);

        $mediaAsset->update([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'duration_seconds' => $request->validated('duration_seconds'),
            'is_active' => $request->has('is_active')
                ? $request->boolean('is_active')
                : $mediaAsset->is_active,
        ]);

        $audit->record('media.updated', $mediaAsset, $before, $this->payload($mediaAsset), $request);

        return response()->json([
            'data' => new MediaAssetResource($mediaAsset),
            'message' => 'Contenido actualizado.',
        ]);
    }

    public function destroy(MediaAsset $mediaAsset, AuditLogger $audit): JsonResponse
    {
        $before = $this->payload($mediaAsset);
        Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);
        $mediaAsset->delete();
        $audit->record('media.deleted', $mediaAsset, $before, null);

        return response()->json([
            'message' => 'Contenido eliminado.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MediaAsset $asset): array
    {
        return [
            'name' => $asset->name,
            'type' => $asset->type,
            'path' => $asset->path,
            'mime' => $asset->mime,
            'file_size' => $asset->file_size,
            'duration_seconds' => $asset->duration_seconds,
            'is_active' => $asset->is_active,
        ];
    }
}
