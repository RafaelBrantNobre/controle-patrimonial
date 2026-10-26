<?php

namespace App\Carga;

/*
| Uma linha da planilha, como ela veio, antes de qualquer interpretação.
| Colunas da planilha: A Patrimônio, B Descrição, C Local, D Estado, E Nome.
*/
final readonly class LinhaDaPlanilha
{
    public function __construct(
        public string $aba,
        public int $linha,
        public string $patrimonio,
        public string $descricao,
        public string $local,
        public string $estado,
        public string $conferente,
    ) {}

    /** Onde a linha está, para o relatório: "Principal, linha 12". */
    public function origem(): string
    {
        return "{$this->aba}, linha {$this->linha}";
    }
}
