<?php

namespace Database\Factories\Domain\Operations;

use App\Domain\Operations\OperatorSession;
use App\Domain\Sections\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorSession>
 */
class OperatorSessionFactory extends Factory
{
    protected $model = OperatorSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => User::factory(),
            'section_id' => Section::factory(),
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
