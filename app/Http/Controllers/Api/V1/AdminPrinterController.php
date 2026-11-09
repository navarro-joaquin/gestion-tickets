<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Printing\Printer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PrinterRequest;
use App\Http\Resources\PrinterResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPrinterController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $printers = Printer::query()
            ->with('kioskDevice')
            ->orderBy('name')
            ->get();

        return PrinterResource::collection($printers);
    }

    public function store(PrinterRequest $request, AuditLogger $audit): JsonResponse
    {
        $printer = Printer::query()->create($request->validated());

        $audit->record('printer.created', $printer, null, $this->payload($printer), $request);

        $printer->load('kioskDevice');

        return response()->json([
            'data' => new PrinterResource($printer),
            'message' => 'Impresora creada.',
        ], 201);
    }

    public function update(
        PrinterRequest $request,
        Printer $printer,
        AuditLogger $audit,
    ): JsonResponse {
        $before = $this->payload($printer);
        $printer->update($request->validated());

        $audit->record('printer.updated', $printer, $before, $this->payload($printer), $request);

        $printer->load('kioskDevice');

        return response()->json([
            'data' => new PrinterResource($printer),
            'message' => 'Impresora actualizada.',
        ]);
    }

    public function destroy(Printer $printer, AuditLogger $audit): JsonResponse
    {
        $before = $this->payload($printer);
        $printer->delete();
        $audit->record('printer.deleted', $printer, $before, null);

        return response()->json([
            'message' => 'Impresora eliminada.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Printer $printer): array
    {
        return [
            'kiosk_device_id' => $printer->kiosk_device_id,
            'name' => $printer->name,
            'driver' => $printer->driver,
            'target' => $printer->target,
            'paper_width' => $printer->paper_width,
            'is_active' => $printer->is_active,
            'config' => $printer->config,
        ];
    }
}
