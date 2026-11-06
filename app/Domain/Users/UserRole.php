<?php

namespace App\Domain\Users;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Operator => 'Operador',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
