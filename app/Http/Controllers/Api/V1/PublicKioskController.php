<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Kiosks\KioskDevice;
use App\Http\Controllers\Controller;
use App\Http\Resources\SectionResource;
use Illuminate\Http\JsonResponse;

class PublicKioskController extends Controller
{
    public function show(KioskDevice $kioskDevice): JsonResponse
    {
        abort_unless($kioskDevice->is_active, 404);

        $kioskDevice->forceFill(['last_seen_at' => now()])->save();

        $sections = $kioskDevice->sections()
            ->where('sections.is_active', true)
            ->with(['ticketCodes.categories', 'services'])
            ->get();

        return response()->json([
            'data' => [
                'id' => $kioskDevice->id,
                'name' => $kioskDevice->name,
                'slug' => $kioskDevice->slug,
                'sections' => SectionResource::collection($sections)->resolve(),
            ],
        ]);
    }
}
