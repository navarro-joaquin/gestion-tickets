<?php

namespace App\Domain\Printing;

use App\Domain\Kiosks\KioskDevice;
use Database\Factories\Domain\Printing\PrinterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kiosk_device_id',
    'name',
    'driver',
    'target',
    'paper_width',
    'is_active',
    'config',
])]
class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'paper_width' => 'integer',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KioskDevice, $this>
     */
    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
