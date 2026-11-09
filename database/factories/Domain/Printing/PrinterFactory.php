<?php

namespace Database\Factories\Domain\Printing;

use App\Domain\Kiosks\KioskDevice;
use App\Domain\Printing\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    protected $model = Printer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kiosk_device_id' => KioskDevice::factory(),
            'name' => 'Impresora '.fake()->unique()->numberBetween(1, 99),
            'driver' => 'local',
            'target' => 'http://127.0.0.1:9100',
            'paper_width' => 80,
            'is_active' => true,
            'config' => null,
        ];
    }

    public function qz(string $target): static
    {
        return $this->state(fn (): array => [
            'driver' => 'qz',
            'target' => $target,
        ]);
    }
}
