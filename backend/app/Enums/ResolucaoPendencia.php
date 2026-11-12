<?php

namespace App\Enums;

/*
| Como a pendência de um bem não localizado foi resolvida.
|   - localizado: o bem apareceu depois. Ele volta a constar como encontrado.
|   - processo_administrativo: a gestora abriu o processo. O número fica
|     guardado, e o bem continua como não localizado até a baixa.
*/
enum ResolucaoPendencia: string
{
    case Localizado = 'localizado';
    case ProcessoAdministrativo = 'processo_administrativo';

    /** Texto para mostrar nas telas. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Localizado => 'Localizado depois',
            self::ProcessoAdministrativo => 'Processo administrativo',
        };
    }
}
