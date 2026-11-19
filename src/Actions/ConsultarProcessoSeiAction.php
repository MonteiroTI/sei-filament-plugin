<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Slide-over com o processo no SEI: tipo, nível de acesso, unidades onde está aberto, interessados, link e andamentos.
 * No Filament 3 cada lugar tem a sua classe de ação, então são três fábricas com a mesma configuração:
 *
 * ```php
 * ConsultarProcessoSeiAction::make();                                   // cabeçalho da página
 * ConsultarProcessoSeiAction::tabela(protocolo: fn ($record) => $record->numero_sei);
 * ConsultarProcessoSeiAction::infolist(conexao: 'homologacao');
 * ```
 *
 * Sem $protocolo: o processo vinculado pelo HasSeiProcesso do conector (seiProcesso) ou o atributo protocolo_sei.
 * Sem o protocolo, a ação some.
 */
final class ConsultarProcessoSeiAction
{
    public const NOME = 'consultarProcessoSei';

    /**
     * @param  (Closure(Model|null): ?string)|string|null  $protocolo
     * @param  (Closure(Model|null): ?string)|string|null  $conexao  padrão: a do vínculo ou a default
     * @param  list<int|string>  $tarefas  além da geração, conclusão e último andamento, lista os andamentos destas tarefas
     */
    public static function make(Closure|string|null $protocolo = null, Closure|string|null $conexao = null, array $tarefas = [], string $nome = self::NOME): Action
    {
        return self::configurar(Action::make($nome), $protocolo, $conexao, $tarefas);
    }

    /**
     * @param  (Closure(Model|null): ?string)|string|null  $protocolo
     * @param  (Closure(Model|null): ?string)|string|null  $conexao
     * @param  list<int|string>  $tarefas
     */
    public static function tabela(Closure|string|null $protocolo = null, Closure|string|null $conexao = null, array $tarefas = [], string $nome = self::NOME): TableAction
    {
        return self::configurar(TableAction::make($nome), $protocolo, $conexao, $tarefas);
    }

    /**
     * @param  (Closure(Model|null): ?string)|string|null  $protocolo
     * @param  (Closure(Model|null): ?string)|string|null  $conexao
     * @param  list<int|string>  $tarefas
     */
    public static function infolist(Closure|string|null $protocolo = null, Closure|string|null $conexao = null, array $tarefas = [], string $nome = self::NOME): InfolistAction
    {
        return self::configurar(InfolistAction::make($nome), $protocolo, $conexao, $tarefas);
    }

    /**
     * @template T of MountableAction
     *
     * @param  T  $acao
     * @param  list<int|string>  $tarefas
     * @return T
     */
    private static function configurar(MountableAction $acao, Closure|string|null $protocolo, Closure|string|null $conexao, array $tarefas): MountableAction
    {
        $protocoloDo = fn (?Model $record): ?string => self::texto($protocolo instanceof Closure ? $acao->evaluate($protocolo, ['record' => $record]) : $protocolo)
            ?? self::texto($record?->getAttribute('seiProcesso')?->protocolo_formatado ?? null)
            ?? self::texto($record?->getAttribute('protocolo_sei'));

        $conexaoDo = fn (?Model $record): ?string => self::texto($conexao instanceof Closure ? $acao->evaluate($conexao, ['record' => $record]) : $conexao)
            ?? self::texto($record?->getAttribute('seiProcesso')?->conexao ?? null);

        return $acao
            ->label('Consultar no SEI')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('gray')
            ->hidden(fn (?Model $record) => $protocoloDo($record) === null)
            ->slideOver()
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(fn (?Model $record) => 'Processo '.$protocoloDo($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn (?Model $record): View => view('sei-filament::actions.consultar-processo', self::consultar(
                (string) $protocoloDo($record),
                $conexaoDo($record),
                $tarefas,
            )));
    }

    /**
     * @param  list<int|string>  $tarefas
     * @return array<string, mixed>
     */
    public static function consultar(string $protocolo, ?string $conexao = null, array $tarefas = []): array
    {
        try {
            $cliente = app(SeiManager::class)->connection($conexao);
            $processo = $cliente->processos()->consultar($protocolo, ConsultaProcedimentoOptions::completo());

            $andamentos = array_values(array_filter([
                $processo->ultimoAndamento,
                $processo->andamentoConclusao,
                $processo->andamentoGeracao,
            ]));

            if ($tarefas !== []) {
                $andamentos = [...$andamentos, ...$cliente->andamentos()->listar($protocolo, tarefas: $tarefas)];
            }

            return ['processo' => $processo, 'andamentos' => self::ordenar($andamentos), 'erro' => null];
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return ['processo' => null, 'andamentos' => [], 'erro' => Erros::titulo($e).': '.Erros::mensagem($e)];
        }
    }

    /**
     * Mais recente primeiro, sem repetir o mesmo andamento.
     *
     * @param  list<Andamento>  $andamentos
     * @return list<Andamento>
     */
    private static function ordenar(array $andamentos): array
    {
        $unicos = [];
        foreach ($andamentos as $andamento) {
            $unicos[$andamento->idAndamento ?? spl_object_id($andamento)] = $andamento;
        }

        usort($unicos, fn (Andamento $a, Andamento $b) => $b->dataHora <=> $a->dataHora);

        return $unicos;
    }

    private static function texto(mixed $valor): ?string
    {
        return is_scalar($valor) && trim((string) $valor) !== '' ? trim((string) $valor) : null;
    }
}
