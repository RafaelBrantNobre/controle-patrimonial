<?php

namespace App\Enums;

/*
| Situação do bem (página 03, seção 12). O banco guarda o texto do "value";
| esta lista é a única fonte dos valores válidos. A lista final depende da PD03.
*/
enum StatusBem: string
{
    case EmUso = 'em_uso';
    case SemUso = 'sem_uso';
    case Avariado = 'avariado';
    case Baixado = 'baixado';

    /** Texto para mostrar nas telas e no relatório. */
    public function rotulo(): string
    {
        return match ($this) {
            self::EmUso => 'Em uso',
            self::SemUso => 'Sem uso',
            self::Avariado => 'Avariado',
            self::Baixado => 'Baixado',
        };
    }
}
