<?php

namespace App\Enums;

/*
| Perfis de acesso do MVP (página 03, seção 12). A lista final depende da PD02.
*/
enum PerfilUsuario: string
{
    case Administrador = 'administrador';
    case Consulta = 'consulta';

    public function rotulo(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Consulta => 'Consulta',
        };
    }
}
