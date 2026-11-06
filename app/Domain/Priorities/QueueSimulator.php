<?php

namespace App\Domain\Priorities;

/**
 * Simula una secuencia de llamados sin tocar la base de datos.
 */
final class QueueSimulator
{
    /**
     * @return list<array{position: int, group: string, reason: string, general_remaining: int, preferential_remaining: int}>
     */
    public function simulate(int $generalCount, int $preferentialCount, PriorityProfile $profile): array
    {
        $generalRemaining = max(0, $generalCount);
        $preferentialRemaining = max(0, $preferentialCount);
        $generalCalls = 0;
        $preferentialCalls = 0;
        $consecutivePreferential = 0;
        $sequence = [];
        $position = 0;

        while ($generalRemaining > 0 || $preferentialRemaining > 0) {
            $position++;

            if ($preferentialRemaining === 0) {
                $group = 'general';
                $reason = 'only_general';
            } elseif ($generalRemaining === 0) {
                $group = 'preferential';
                $reason = 'only_preferential';
            } else {
                $targetPreferential = intdiv(
                    ($generalCalls + $preferentialCalls + 1) * $profile->preferential_ratio,
                    $profile->totalRatio(),
                );

                $group = $preferentialCalls < $targetPreferential ? 'preferential' : 'general';
                $reason = 'ratio';

                if ($group === 'preferential' && $consecutivePreferential >= $profile->max_consecutive_preferential) {
                    $group = 'general';
                    $reason = 'max_consecutive_preferential';
                }
            }

            if ($group === 'general') {
                $generalRemaining--;
                $generalCalls++;
                $consecutivePreferential = 0;
            } else {
                $preferentialRemaining--;
                $preferentialCalls++;
                $consecutivePreferential++;
            }

            $sequence[] = [
                'position' => $position,
                'group' => $group,
                'reason' => $reason,
                'general_remaining' => $generalRemaining,
                'preferential_remaining' => $preferentialRemaining,
            ];
        }

        return $sequence;
    }
}
