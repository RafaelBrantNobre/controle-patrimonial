<?php

namespace App\Enums;

/*
| Situação de uma conferência.
|   - aberta: recebe os resultados, bem a bem. Nada muda nos bens ainda.
|   - concluida: os resultados foram aplicados aos bens (sala, conservação,
|     data e resultado da última conferência) e não mudam mais.
|   - cancelada: gerada por engano ou abandonada. Não muda nada nos bens.
*/
enum SituacaoConferencia: string
{
    case Aberta = 'aberta';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    /** Texto para mostrar nas telas. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }
}
