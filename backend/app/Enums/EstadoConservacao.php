<?php

namespace App\Enums;

/*
| Estado de conservação do bem: a condição física dele, conferida no
| levantamento. É independente da situação de uso (StatusBem): um bem pode
| estar em uso e em estado ruim, ou sem uso e novo.
|
| A escala é a que a equipe já usa nas abas de sala da planilha de
| novembro de 2024, mais "indeterminado" para o bem que ainda não foi
| conferido ou cujo estado não se sabe (o antigo "não sei se funciona").
| O banco guarda o texto do "value"; esta lista é a única fonte dos valores.
*/
enum EstadoConservacao: string
{
    case Novo = 'novo';
    case Bom = 'bom';
    case Regular = 'regular';
    case Pessimo = 'pessimo';
    case Sucata = 'sucata';
    case Indeterminado = 'indeterminado';

    /** Texto para mostrar nas telas e no relatório. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Bom => 'Bom',
            self::Regular => 'Regular',
            self::Pessimo => 'Péssimo',
            self::Sucata => 'Sucata',
            self::Indeterminado => 'Indeterminado',
        };
    }
}
