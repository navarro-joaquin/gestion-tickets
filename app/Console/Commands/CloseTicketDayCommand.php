<?php

namespace App\Console\Commands;

use App\Domain\Settings\DayClosingSchedule;
use App\Domain\Ticketing\Actions\CloseTicketDay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:day:close {--force : Ejecuta el cierre sin esperar la hora configurada}')]
#[Description('Cierra la jornada: expira en espera, marca no presentados los llamados y finaliza los atendidos')]
class CloseTicketDayCommand extends Command
{
    public function handle(CloseTicketDay $action, DayClosingSchedule $schedule): int
    {
        if (! $this->option('force') && ! $schedule->shouldCloseNow()) {
            $this->components->info(
                "Aún no es la hora de cierre configurada ({$schedule->closingTime()}).",
            );

            return self::SUCCESS;
        }

        $result = $action->handle();

        $this->components->info(
            "Cierre de jornada: {$result['expired']} expirados, {$result['no_show']} no presentados y {$result['finished']} finalizados.",
        );

        return self::SUCCESS;
    }
}
