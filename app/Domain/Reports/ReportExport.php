<?php

namespace App\Domain\Reports;

use App\Models\User;
use Database\Factories\Domain\Reports\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'type',
    'format',
    'status',
    'filters',
    'rows',
    'file_path',
    'file_size',
    'error',
    'started_at',
    'completed_at',
])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportExportStatus::class,
            'filters' => 'array',
            'rows' => 'integer',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ReportExportStatus::Completed && $this->file_path !== null;
    }
}
