<?php

namespace App\Enums;

/*
| Resultado de um bem numa conferência: foi encontrado ou não foi localizado.
| Enquanto a pessoa não lança o resultado, o item fica pendente (o banco
| guarda nulo).
|
| Bem não localizado é grave: a gestora responde por ele e precisa abrir
| processo administrativo. Por isso ele vira uma pendência quando a
| conferência é concluída (ver Bem::pendenciasAbertas()).
*/
enum ResultadoConferencia: string
{
    case Encontrado = 'encontrado';
    case NaoLocalizado = 'nao_localizado';

    /** Texto para mostrar nas telas. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Encontrado => 'Encontrado',
            self::NaoLocalizado => 'Não localizado',
        };
    }
}
