<?php

namespace Database\Factories\Domain\Operations;

use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\Station;
use App\Domain\Operations\StationAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StationAssignment>
 */
class StationAssignmentFactory extends Factory
{
    protected $model = StationAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_session_id' => OperatorSession::factory(),
            'station_id' => Station::factory(),
            'assigned_at' => now(),
            'released_at' => null,
        ];
    }
}
