<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Printing\Printer;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrinterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PrinterConfigController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
        ]);

        $printers = Printer::query()
            ->with('station.section')
            ->where('is_active', true)
            ->when(
                isset($validated['station_id']),
                fn ($query) => $query->where('station_id', $validated['station_id']),
            )
            ->orderBy('name')
            ->get();

        return PrinterResource::collection($printers);
    }
}
