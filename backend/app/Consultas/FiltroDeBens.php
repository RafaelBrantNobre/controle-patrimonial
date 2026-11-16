<?php

namespace App\Consultas;

use App\Enums\ResultadoConferencia;
use App\Enums\SituacaoConferencia;
use App\Models\Bem;
use Illuminate\Database\Eloquent\Builder;

/*
| Os filtros da consulta de bens (RF12, RF13), num lugar só. Servem para a
| lista de bens (GET /api/bens) e para gerar uma conferência com todos os
| bens de um filtro (POST /api/conferencias), para as duas telas
| selecionarem exatamente os mesmos bens.
|
| A consulta já junta sala e tipo, que as ordenações usam. A sala entra
| por left join, porque o bem pode estar sem localização (sala nula). A
| ordenação e a paginação ficam com quem chama.
*/
class FiltroDeBens
{
    /** Valores aceitos no filtro de conferência (?conferencia=). */
    public const SITUACOES_DE_CONFERENCIA = ['nunca', 'encontrado', 'nao_localizado', 'em_aberto'];

    /**
     * @param  array<string, mixed>  $filtros  já validados (ListarBensRequest)
     * @return Builder<Bem>
     */
    public static function consulta(array $filtros): Builder
    {
        return Bem::query()
            ->select('bem.*')
            ->leftJoin('sala', 'sala.id', '=', 'bem.sala_id')
            ->join('tipo_patrimonio', 'tipo_patrimonio.id', '=', 'bem.tipo_patrimonio_id')
            ->when($filtros['numero'] ?? null, function ($query, string $numero) {
                $query->where(fn ($query) => $query
                    ->where('bem.numero_patrimonio', $numero)
                    ->orWhere('bem.codigo_interno', $numero));
            })
            ->when($filtros['busca'] ?? null, function ($query, string $busca) {
                $padrao = Busca::contem($busca);
                $query->where(fn ($query) => $query
                    ->where('bem.numero_patrimonio', 'like', $padrao)
                    ->orWhere('bem.codigo_interno', 'like', $padrao)
                    ->orWhere('bem.descricao', 'like', $padrao)
                    ->orWhere('bem.marca', 'like', $padrao));
            })
            ->when($filtros['sem_sala'] ?? false, fn ($query) => $query->whereNull('bem.sala_id'))
            ->when($filtros['sala_id'] ?? null, fn ($query, int $salaId) => $query->where('bem.sala_id', $salaId))
            ->when($filtros['tipo_patrimonio_id'] ?? null, fn ($query, int $tipoId) => $query->where('bem.tipo_patrimonio_id', $tipoId))
            ->when($filtros['status'] ?? null, fn ($query, array $status) => $query->whereIn('bem.status', $status))
            ->when($filtros['estado_conservacao'] ?? null, fn ($query, array $estados) => $query->whereIn('bem.estado_conservacao', $estados))
            // As quatro situações não se sobrepõem e seguem o selo da tela: o
            // bem numa conferência aberta fica só em "em_aberto", mesmo que
            // nunca tenha sido conferido ou tenha resultado de uma anterior.
            ->when($filtros['conferencia'] ?? null, fn ($query, string $situacao) => match ($situacao) {
                'nunca' => $query->whereNull('bem.data_ultima_conferencia')->whereDoesntHave('itensDeConferencia', self::emConferenciaAberta(...)),
                'encontrado' => $query->where('bem.resultado_ultima_conferencia', ResultadoConferencia::Encontrado)->whereDoesntHave('itensDeConferencia', self::emConferenciaAberta(...)),
                'nao_localizado' => $query->where('bem.resultado_ultima_conferencia', ResultadoConferencia::NaoLocalizado)->whereDoesntHave('itensDeConferencia', self::emConferenciaAberta(...)),
                'em_aberto' => $query->whereHas('itensDeConferencia', self::emConferenciaAberta(...)),
            });
    }

    /** Restringe os itens de conferência aos da conferência aberta. */
    private static function emConferenciaAberta(Builder $query): void
    {
        $query->whereHas('conferencia', fn ($query) => $query->where('situacao', SituacaoConferencia::Aberta));
    }
}
