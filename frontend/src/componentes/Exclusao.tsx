import { ConfirmarExclusao, Botao, Entrada, Aviso, Carregando } from '../components/ui'
import { AlertTriangle, Ban, Trash2 } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { ErroDaApi } from '../services/api'

// Um item que impede a exclusão: um bem da sala ou do tipo.
export type Vinculo = { id: number; titulo: string; detalhe?: string; para?: string }

export type Vinculos = { total: number; itens: Vinculo[] }

// Botão Excluir de sala e tipo (RN08).
// Ao clicar, confere o que está ligado ao item:
//   - se houver algo, o popup lista o que está ligado e avisa que não dá
//     para excluir (o caminho é inativar);
//   - se estiver vazio, o popup avisa que a exclusão é permanente e só libera
//     o botão depois que a pessoa digita o nome do item.
// Quem decide de verdade é a API, que responde 409 quando há vínculo; essa
// mensagem também aparece no popup (por exemplo, sala que está no histórico).
export function BotaoExcluir({
  rotulo,
  nome,
  buscarVinculos,
  nomeDoVinculo,
  excluir,
  aoExcluir,
  children,
  className = 'botao border border-red-200 bg-white text-red-700 hover:bg-red-50',
}: {
  rotulo: string
  nome: string
  buscarVinculos: () => Promise<Vinculos>
  nomeDoVinculo: { singular: string; plural: string; feminino?: boolean }
  excluir: () => Promise<unknown>
  aoExcluir: () => void
  children?: ReactNode
  className?: string
}) {
  const [aberto, setAberto] = useState(false)

  return (
    <>
      <Botao type="button" className={className} onClick={() => setAberto(true)}>
        {children ?? (
          <>
            <Trash2 className="size-4" /> Excluir
          </>
        )}
      </Botao>
      {aberto && (
        <PopupDeExclusao
          rotulo={rotulo}
          nome={nome}
          buscarVinculos={buscarVinculos}
          nomeDoVinculo={nomeDoVinculo}
          excluir={excluir}
          aoFechar={() => setAberto(false)}
          aoExcluir={() => {
            setAberto(false)
            aoExcluir()
          }}
        />
      )}
    </>
  )
}

function PopupDeExclusao({
  rotulo,
  nome,
  buscarVinculos,
  nomeDoVinculo,
  excluir,
  aoFechar,
  aoExcluir,
}: {
  rotulo: string
  nome: string
  buscarVinculos: () => Promise<Vinculos>
  nomeDoVinculo: { singular: string; plural: string; feminino?: boolean }
  excluir: () => Promise<unknown>
  aoFechar: () => void
  aoExcluir: () => void
}) {
  const [vinculos, setVinculos] = useState<Vinculos | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [digitado, setDigitado] = useState('')
  const [excluindo, setExcluindo] = useState(false)

  useEffect(() => {
    let cancelado = false
    buscarVinculos()
      .then((resultado) => !cancelado && setVinculos(resultado))
      .catch((e: unknown) => !cancelado && setErro(e instanceof Error ? e.message : 'Não foi possível conferir o que está ligado.'))
    return () => {
      cancelado = true
    }
    // Confere uma vez, ao abrir o popup.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function confirmar() {
    setExcluindo(true)
    setErro(null)
    try {
      await excluir()
      aoExcluir()
    } catch (e) {
      setErro(e instanceof ErroDaApi ? e.message : 'Não foi possível excluir.')
      setExcluindo(false)
    }
  }

  const bloqueado = vinculos !== null && vinculos.total > 0
  const confere = digitado.trim() === nome.trim()

  return (
    <ConfirmarExclusao
      titulo={
        <>
          {bloqueado ? <Ban className="size-5 text-red-600" /> : <AlertTriangle className="size-5 text-red-600" />}
          {bloqueado ? `Não é possível excluir ${rotulo}` : `Excluir ${rotulo}`}
        </>
      }
      aoFechar={aoFechar}
      aoConfirmar={() => void confirmar()}
      ocupado={excluindo}
      desabilitado={!confere}
      ocultarConfirmacao={vinculos === null || bloqueado}
      cancelar={bloqueado ? 'Entendi' : 'Cancelar'}
    >
      {vinculos === null && !erro && <Carregando texto="Conferindo o que está ligado..." />}

      {bloqueado && (
        <div className="space-y-3 text-sm text-slate-600">
          <p>
            <strong className="text-slate-800">{nome}</strong> tem{' '}
            {vinculos.total === 1
              ? `1 ${nomeDoVinculo.singular} ${nomeDoVinculo.feminino ? 'ligada' : 'ligado'}`
              : `${vinculos.total} ${nomeDoVinculo.plural} ${nomeDoVinculo.feminino ? 'ligadas' : 'ligados'}`}
            , então a exclusão não é possível. Mova ou corrija esses itens antes. Se não for mais usar {rotulo}, inative em vez de excluir.
          </p>
          <ul className="max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
            {vinculos.itens.map((item) => (
              <li key={item.id} className="px-3 py-2">
                {item.para ? (
                  <Link to={item.para} className="font-medium text-cead-700 hover:underline">
                    {item.titulo}
                  </Link>
                ) : (
                  <span className="font-medium text-slate-800">{item.titulo}</span>
                )}
                {item.detalhe && <span className="block text-xs text-slate-500">{item.detalhe}</span>}
              </li>
            ))}
          </ul>
          {vinculos.total > vinculos.itens.length && (
            <p className="text-xs text-slate-500">
              Mostrando {vinculos.itens.length} de {vinculos.total}.
            </p>
          )}
        </div>
      )}

      {vinculos !== null && !bloqueado && (
        <div className="space-y-3 text-sm text-slate-600">
          <p>
            Tem certeza de que quer excluir <strong className="text-slate-800">{nome}</strong>? A exclusão é{' '}
            <strong className="text-red-700">permanente</strong> e não pode ser desfeita.
          </p>
          <label className="block">
            <span className="mb-1 block">
              Para confirmar, digite <strong className="text-slate-800">{nome}</strong>:
            </span>
            <Entrada
              value={digitado}
              onChange={(e) => setDigitado(e.target.value)}
              autoFocus
              autoComplete="off"
              aria-label={`Digite ${nome} para confirmar`}
            />
          </label>
        </div>
      )}

      {erro && (
        <div className="mt-3">
          <Aviso>{erro}</Aviso>
        </div>
      )}
    </ConfirmarExclusao>
  )
}
