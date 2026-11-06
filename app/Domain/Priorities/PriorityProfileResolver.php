<?php

namespace App\Domain\Priorities;

use Carbon\CarbonInterface;

final class PriorityProfileResolver
{
    public function resolve(CarbonInterface $moment): PriorityProfile
    {
        $profile = PriorityProfile::query()
            ->effectiveOn($moment)
            ->orderBy('id')
            ->first();

        return $profile ?? $this->fallback();
    }

    /**
     * Perfil usado cuando la base de datos aún no tiene perfiles configurados.
     */
    public function fallback(): PriorityProfile
    {
        return new PriorityProfile([
            'name' => 'Estándar 2:1',
            'description' => 'Perfil por defecto: 2 generales por cada preferencial.',
            'general_ratio' => 2,
            'preferential_ratio' => 1,
            'aging_minutes' => 30,
            'max_consecutive_preferential' => 1,
            'is_active' => true,
        ]);
    }
}
