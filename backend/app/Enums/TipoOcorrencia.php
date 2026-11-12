<?php

namespace App\Enums;

/*
| Tipo de ocorrência registrada no histórico de um bem.
*/
enum TipoOcorrencia: string
{
    case Sala = 'sala';
    case Conservacao = 'conservacao';
    case Uso = 'uso';
    case Baixa = 'baixa';

    /** Texto para mostrar nas telas. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Sala => 'Mudança de sala',
            self::Conservacao => 'Estado de conservação',
            self::Uso => 'Condição de uso',
            self::Baixa => 'Baixa',
        };
    }
}
