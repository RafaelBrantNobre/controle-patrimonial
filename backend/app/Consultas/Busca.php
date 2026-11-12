<?php

namespace App\Consultas;

/*
| Busca por "contém o termo" com LIKE, usada nas listagens.
*/
final class Busca
{
    /**
     * Padrão para buscar "contém o termo" com LIKE. Os caracteres % e _
     * digitados pelo usuário valem como texto, e não como curinga.
     */
    public static function contem(string $termo): string
    {
        return '%'.addcslashes($termo, '%_\\').'%';
    }
}
