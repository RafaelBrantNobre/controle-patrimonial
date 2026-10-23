<?php

namespace App\Relatorios;

use App\Enums\StatusBem;
use App\Models\Bem;
use App\Models\Movimentacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/*
| Relatório do levantamento patrimonial (tarefa 2.10, RF22 a RF26).
|
| Traz todos os bens que passam nos filtros, na ordem da conferência
| física: andar, sala, tipo e número de patrimônio (RF23). O período não
| corta a lista: ele define quais bens aparecem em destaque (RF25).
|
| Como se sabe que um bem mudou no período:
|   - de sala: há uma linha em movimentacao dentro do período (tarefa 2.8).
|     A primeira delas diz de qual sala o bem veio.
|   - de qualquer tipo: a data da última mudança cai no período. Ela cobre
|     também a mudança de situação, que não tem histórico próprio.
| Limite conhecido: num período passado, se o bem mudou de situação dentro
| dele e mudou de novo depois, a mudança de situação não aparece, porque
| só a data da última mudança fica gravada. A mudança de sala sempre aparece.
|
| A mesma instância serve à resposta em JSON e à planilha.
*/
class RelatorioDoLevantamento
{
    public readonly CarbonImmutable $inicio;

    public readonly CarbonImmutable $fim;

    /** @var Collection<int, Bem>|null */
    private ?Collection $bens = null;

    /** @var Collection<int, Movimentacao> primeira mudança de sala no período, por bem */
    private Collection $primeiraMudancaDeSala;

    /**
     * @param  array<string, mixed>  $filtros  dados validados por RelatorioRequest
     */
    public function __construct(private readonly array $filtros)
    {
        $this->inicio = CarbonImmutable::parse($filtros['inicio'])->startOfDay();
        $this->fim = CarbonImmutable::parse($filtros['fim'])->endOfDay();
    }

    /**
     * Os bens do relatório, já na ordem da conferência, com as relações que
     * o relatório mostra. Cada bem ganha dois atributos calculados,
     * mudou_no_periodo e mudou_de_sala_no_periodo, e a relação veioDaSala,
     * que é a sala de onde ele saiu na primeira mudança do período.
     *
     * @return Collection<int, Bem>
     */
    public function bens(): Collection
    {
        if ($this->bens !== null) {
            return $this->bens;
        }

        $bens = Bem::query()
            ->select('bem.*')
            ->join('sala', 'sala.id', '=', 'bem.sala_id')
            ->join('andar', 'andar.id', '=', 'sala.andar_id')
            ->join('tipo_patrimonio', 'tipo_patrimonio.id', '=', 'bem.tipo_patrimonio_id')
            ->with(['sala.andar', 'tipoPatrimonio'])
            ->when($this->filtros['andar_id'] ?? null, fn ($query, $id) => $query->where('andar.id', $id))
            ->when($this->filtros['sala_id'] ?? null, fn ($query, $id) => $query->where('bem.sala_id', $id))
            ->when($this->filtros['tipo_patrimonio_id'] ?? null, fn ($query, $id) => $query->where('bem.tipo_patrimonio_id', $id))
            ->when($this->filtros['status'] ?? null, fn ($query, array $status) => $query->whereIn('bem.status', $status))
            ->when($this->filtros['apenas_mudancas'] ?? false, fn ($query) => $query->where(fn ($query) => $query
                ->whereBetween('bem.data_ultima_mudanca', [$this->inicio->toDateString(), $this->fim->toDateString()])
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('movimentacao')
                    ->whereColumn('movimentacao.bem_id', 'bem.id')
                    ->whereBetween('movimentacao.data', [$this->inicio, $this->fim]))))
            ->orderBy('andar.ordem')
            ->orderBy('andar.nome')
            ->orderBy('sala.nome')
            ->orderBy('tipo_patrimonio.nome')
            ->orderBy('bem.numero_patrimonio')
            ->orderBy('bem.id')
            ->get();

        $this->primeiraMudancaDeSala = Movimentacao::query()
            ->with('salaOrigem.andar')
            ->whereIn('bem_id', $bens->modelKeys())
            ->whereBetween('data', [$this->inicio, $this->fim])
            ->orderBy('data')
            ->orderBy('id')
            ->get()
            ->unique('bem_id')
            ->keyBy('bem_id');

        foreach ($bens as $bem) {
            $mudancaDeSala = $this->primeiraMudancaDeSala->get($bem->id);
            $dataDaUltima = $bem->data_ultima_mudanca;

            $bem->mudou_de_sala_no_periodo = $mudancaDeSala !== null;
            $bem->setRelation('veioDaSala', $mudancaDeSala?->salaOrigem);
            $bem->mudou_no_periodo = $mudancaDeSala !== null
                || ($dataDaUltima !== null && $dataDaUltima->between($this->inicio, $this->fim));
        }

        return $this->bens = $bens;
    }

    /**
     * Totais para o topo do relatório.
     *
     * @return array<string, mixed>
     */
    public function totais(): array
    {
        $bens = $this->bens();

        return [
            'bens' => $bens->count(),
            'mudaram_no_periodo' => $bens->where('mudou_no_periodo', true)->count(),
            'mudaram_de_sala_no_periodo' => $bens->where('mudou_de_sala_no_periodo', true)->count(),
            'por_status' => collect(StatusBem::cases())
                ->mapWithKeys(fn (StatusBem $status) => [$status->value => $bens->where('status', $status)->count()])
                ->all(),
        ];
    }
}
