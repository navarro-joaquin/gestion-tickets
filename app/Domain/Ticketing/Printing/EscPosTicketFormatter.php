<?php

namespace App\Domain\Ticketing\Printing;

use App\Domain\Ticketing\Ticket;

final class EscPosTicketFormatter
{
    private const INIT = "\x1B\x40";

    private const CODEPAGE_850 = "\x1B\x74\x02";

    private const ALIGN_LEFT = "\x1B\x61\x00";

    private const ALIGN_CENTER = "\x1B\x61\x01";

    private const BOLD_ON = "\x1B\x45\x01";

    private const BOLD_OFF = "\x1B\x45\x00";

    private const DOUBLE_ON = "\x1D\x21\x11";

    private const DOUBLE_OFF = "\x1D\x21\x00";

    private const FEED = "\x1B\x64\x03";

    private const CUT = "\x1D\x56\x00";

    public function format(Ticket $ticket): string
    {
        $ticket->loadMissing(['section', 'category']);

        return self::INIT.self::CODEPAGE_850.self::ALIGN_CENTER.self::BOLD_ON
            .$this->encode($ticket->section->name)."\n"
            .self::BOLD_OFF
            .$this->encode($ticket->category->name)."\n\n"
            .self::DOUBLE_ON.$this->encode($ticket->label)."\n".self::DOUBLE_OFF
            ."\n"
            .$this->encode($ticket->issued_at->format('d/m/Y H:i'))."\n\n"
            .$this->encode('Espere su llamado')."\n"
            .self::ALIGN_LEFT.self::FEED.self::CUT;
    }

    public function preview(Ticket $ticket): string
    {
        $ticket->loadMissing(['section', 'category']);

        return implode("\n", [
            $ticket->section->name,
            $ticket->category->name,
            $ticket->label,
            $ticket->issued_at->format('d/m/Y H:i'),
            'Espere su llamado',
        ]);
    }

    private function encode(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP850//TRANSLIT', $text);

        return $converted === false ? $text : $converted;
    }
}
