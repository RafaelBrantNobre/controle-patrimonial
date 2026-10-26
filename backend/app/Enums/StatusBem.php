<?php

namespace App\Enums;

/*
| Situação de uso do bem: se ele está sendo usado, parado ou já baixado.
| A condição física (quebrado, ruim, novo) é outra informação, o estado de
| conservação (EstadoConservacao). Um computador quebrado fica "sem uso",
| com conservação "péssimo" ou "sucata".
|
| O banco guarda o texto do "value" na coluna status; esta lista é a única
| fonte dos valores válidos.
*/
enum StatusBem: string
{
    case EmUso = 'em_uso';
    case SemUso = 'sem_uso';
    case Baixado = 'baixado';

    /** Texto para mostrar nas telas e no relatório. */
    public function rotulo(): string
    {
        return match ($this) {
            self::EmUso => 'Em uso',
            self::SemUso => 'Sem uso',
            self::Baixado => 'Baixado',
        };
    }
}
