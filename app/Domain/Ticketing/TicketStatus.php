<?php

namespace App\Domain\Ticketing;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InService = 'in_service';
    case Finished = 'finished';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Called => 'Llamado',
            self::InService => 'En atención',
            self::Finished => 'Finalizado',
            self::NoShow => 'No presentado',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Called, self::InService], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Finished, self::NoShow, self::Cancelled, self::Expired], true);
    }

    /**
     * Transiciones que pueden provocar los operadores o el sistema dentro de una sección.
     *
     * No incluye volver a "en espera": el reencolado manual no está permitido. La única
     * vía para que un ticket llamado regrese a la cola es una transferencia a otra
     * sección (ver {@see self::isTransferable()}), que lo reencola en la sección destino.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Waiting => [self::Called, self::Cancelled, self::Expired],
            self::Called => [self::InService, self::NoShow, self::Cancelled],
            self::InService => [self::Finished],
            self::Finished, self::NoShow, self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Indica si el ticket puede transferirse a otra sección.
     *
     * Un ticket en espera conserva su estado; uno llamado se libera de la ventanilla
     * y vuelve a "en espera" en la sección destino.
     */
    public function isTransferable(): bool
    {
        return in_array($this, [self::Waiting, self::Called], true);
    }
}
