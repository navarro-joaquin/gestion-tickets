<?php

namespace App\Http\Resources;

use App\Domain\Reports\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportExport
 */
class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'format' => $this->format,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'filters' => $this->filters,
            'rows' => $this->rows,
            'file_size' => $this->file_size,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded(
                'user',
                fn (): ?array => $this->user === null ? null : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
            ),
            'download_url' => $this->when(
                $this->isDownloadable(),
                fn (): string => route('api.v1.admin.reports.exports.download', $this->resource),
            ),
        ];
    }
}
