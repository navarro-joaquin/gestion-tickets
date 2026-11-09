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

        $printer = $kioskDevice->printers()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return response()->json([
            'data' => [
                'id' => $kioskDevice->id,
                'name' => $kioskDevice->name,
                'slug' => $kioskDevice->slug,
                'printer' => $printer === null ? null : [
                    'driver' => $printer->driver,
                    'target' => $printer->target,
                    'paper_width' => $printer->paper_width,
                ],
                'sections' => SectionResource::collection($sections)->resolve(),
            ],
        ]);
    }
}
