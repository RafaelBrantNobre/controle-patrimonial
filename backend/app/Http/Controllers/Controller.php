<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /*
    | Itens por página das listagens (RNF03). O front pode pedir outro valor
    | com ?por_pagina=, entre 1 e 100. A página vem em ?pagina=.
    */
    protected const POR_PAGINA_PADRAO = 20;

    protected const POR_PAGINA_MAXIMO = 100;

    protected function porPagina(Request $request): int
    {
        $porPagina = $request->integer('por_pagina', self::POR_PAGINA_PADRAO);

        return max(1, min($porPagina, self::POR_PAGINA_MAXIMO));
    }

    /**
     * Padrão para buscar "contém o termo" com LIKE. Os caracteres % e _
     * digitados pelo usuário valem como texto, e não como curinga.
     */
    protected function padraoDeBusca(string $termo): string
    {
        return '%'.addcslashes($termo, '%_\\').'%';
    }
}
