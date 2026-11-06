<?php

namespace App\Console\Commands;

use App\Domain\Sections\Section;
use App\Domain\Sections\SectionActivationGate;
use App\Domain\Sections\SectionMatrixValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:codes:verify {--strict : Falla si una sección está inactiva o sin matriz confirmada}')]
#[Description('Verifica la matriz código→categoría de todas las secciones')]
class VerifyTicketCodes extends Command
{
    public function handle(
        SectionMatrixValidator $validator,
        SectionActivationGate $gate,
    ): int {
        $sections = Section::query()
            ->with('ticketCodes.categories')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        if ($sections->isEmpty()) {
            $this->components->error('No hay secciones configuradas.');

            return self::FAILURE;
        }

        $strict = (bool) $this->option('strict');
        $failed = false;
        $rows = [];

        foreach ($sections as $section) {
            $result = $validator->validate($section);
            $valid = $result->isValid();
            $operational = $gate->isOperational($section);
            $sectionFailed = ! $valid || ($strict && ! $operational);

            $failed = $failed || $sectionFailed;

            $rows[] = [
                $section->slug,
                $section->ticketCodes->pluck('code')->implode(', '),
                $valid ? 'OK' : 'ERROR',
                $operational ? 'Sí' : 'No',
                $result->errors() === [] ? '—' : implode(' | ', $result->errors()),
            ];
        }

        $this->table(
            ['Sección', 'Códigos', 'Matriz', 'Operativa', 'Errores'],
            $rows,
        );

        if ($failed) {
            $this->components->error('La verificación encontró secciones con problemas.');

            return self::FAILURE;
        }

        $this->components->info('Todas las secciones tienen una matriz válida.');

        return self::SUCCESS;
    }
}
