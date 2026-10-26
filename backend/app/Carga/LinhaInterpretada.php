<?php

namespace App\Carga;

/*
| O que a carga entendeu de uma linha da planilha.
|
| Se $motivoDaRejeicao estiver preenchido, a linha não entra no sistema e
| o motivo vai para o relatório da carga. Senão, os demais campos são os
| dados do bem, e $avisos lista o que merece conferência, mesmo tendo entrado.
*/
final class LinhaInterpretada
{
    /** @var array<int, string> */
    public array $avisos = [];

    public ?string $motivoDaRejeicao = null;

    public ?string $numeroPatrimonio = null;

    public ?string $tipo = null;

    public ?string $sala = null;

    public ?string $descricao = null;

    public ?string $marca = null;

    public ?string $origem = null;

    public ?string $status = null;

    public ?string $estadoConservacao = null;

    public ?string $observacao = null;

    public function __construct(public readonly LinhaDaPlanilha $linha) {}

    public function rejeitar(string $motivo): self
    {
        $this->motivoDaRejeicao = $motivo;

        return $this;
    }

    public function foiRejeitada(): bool
    {
        return $this->motivoDaRejeicao !== null;
    }
}
