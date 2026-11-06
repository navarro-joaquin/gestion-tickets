<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\ReportExport;
use App\Domain\Reports\ReportExportStatus;
use App\Domain\Reports\TicketReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Http\Requests\Api\V1\StoreReportExportRequest;
use App\Http\Resources\ReportExportResource;
use App\Http\Resources\TicketResource;
use App\Jobs\GenerateReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function summary(ReportFilterRequest $request, TicketReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->summary($request->filters()),
        ]);
    }

    public function tickets(
        ReportFilterRequest $request,
        TicketReport $report,
    ): AnonymousResourceCollection {
        return TicketResource::collection(
            $report->tickets($request->filters(), $request->perPage()),
        );
    }

    public function exports(): AnonymousResourceCollection
    {
        $exports = ReportExport::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);

        return ReportExportResource::collection($exports);
    }

    public function storeExport(StoreReportExportRequest $request): JsonResponse
    {
        $export = ReportExport::query()->create([
            'user_id' => $request->user()?->id,
            'type' => 'tickets',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued,
            'filters' => $request->filters()->toArray(),
        ]);

        GenerateReportExport::dispatch($export);

        $export->load('user');

        return response()->json([
            'data' => new ReportExportResource($export),
            'message' => 'Exportación en cola. Estará disponible en unos instantes.',
        ], 202);
    }

    public function download(ReportExport $export): StreamedResponse
    {
        abort_unless($export->isDownloadable(), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($export->file_path), 404);

        return $disk->download(
            $export->file_path,
            "tickets-export-{$export->id}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function destroyExport(ReportExport $export): JsonResponse
    {
        if ($export->file_path !== null) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->delete();

        return response()->json([
            'message' => 'Exportación eliminada.',
        ]);
    }
}
