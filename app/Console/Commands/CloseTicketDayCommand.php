<?php

namespace App\Console\Commands;

use App\Domain\Ticketing\Actions\CloseTicketDay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:day:close')]
#[Description('Expira los tickets en espera y finaliza los activos al cierre de la jornada')]
class CloseTicketDayCommand extends Command
{
    public function handle(CloseTicketDay $action): int
    {
        $result = $action->handle();

        $this->components->info(
            "Cierre de jornada: {$result['expired']} tickets expirados y {$result['finished']} finalizados.",
        );

        return self::SUCCESS;
    }
}
