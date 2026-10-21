<?php

namespace App\Models;

use App\Enums\StatusBem;
use Database\Factories\BemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| O item patrimonial, entidade central do sistema.
| Bem nunca é apagado: a saída do patrimônio ativo é a baixa (RN07).
| A geração do codigo_interno e as regras de mudança de sala e de status
| ficam nas tarefas 2.6 e 2.8.
*/
#[Table('bem')]
#[Fillable([
    'numero_patrimonio',
    'codigo_interno',
    'tipo_patrimonio_id',
    'sala_id',
    'descricao',
    'marca',
    'origem',
    'quantidade',
    'status',
    'observacao',
    'data_ultima_mudanca',
    'usuario_ultima_mudanca_id',
    'baixa_data',
    'baixa_observacao',
])]
class Bem extends Model
{
    /** @use HasFactory<BemFactory> */
    use HasFactory;

    // O Laravel lê o padrão do banco só depois de salvar. Repetir aqui faz um
    // bem novo, ainda não salvo, já nascer em uso e com quantidade 1.
    protected $attributes = [
        'status' => 'em_uso',
        'quantidade' => 1,
    ];

    /**
     * @return BelongsTo<TipoPatrimonio, $this>
     */
    public function tipoPatrimonio(): BelongsTo
    {
        return $this->belongsTo(TipoPatrimonio::class);
    }

    /**
     * @return BelongsTo<Sala, $this>
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Quem fez a última mudança de sala ou de status (RN09).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuarioUltimaMudanca(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_ultima_mudanca_id');
    }

    public function estaBaixado(): bool
    {
        return $this->status === StatusBem::Baixado;
    }

    protected function casts(): array
    {
        return [
            'status' => StatusBem::class,
            'quantidade' => 'integer',
            'data_ultima_mudanca' => 'date',
            'baixa_data' => 'date',
        ];
    }
}
