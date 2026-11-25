<?php

namespace MonteiroTI\SeiFilament\Actions\Concerns;

use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Tables\Actions\Action as TableAction;

/**
 * No Filament 3 cada lugar tem a sua classe de ação: make() (cabeçalho de página), tabela() e infolist() montam a
 * mesma ação com as opções nomeadas de configurar() da classe.
 *
 * ```php
 * GerarProcessoSeiAction::tabela(conexao: 'homologacao', naFila: true);
 * ```
 */
trait FabricasDeAcao
{
    public static function make(?string $nome = null, mixed ...$opcoes): Action
    {
        return static::configurar(Action::make($nome ?? static::NOME), ...$opcoes);
    }

    public static function tabela(?string $nome = null, mixed ...$opcoes): TableAction
    {
        return static::configurar(TableAction::make($nome ?? static::NOME), ...$opcoes);
    }

    public static function infolist(?string $nome = null, mixed ...$opcoes): InfolistAction
    {
        return static::configurar(InfolistAction::make($nome ?? static::NOME), ...$opcoes);
    }

    /**
     * Valor de uma opção: fixa ou Closure avaliada com o registro (e os dados do formulário, se houver).
     *
     * @param  array<string, mixed>  $extras
     */
    protected static function avaliar(MountableAction $acao, mixed $opcao, array $extras = []): mixed
    {
        return $opcao instanceof \Closure ? $acao->evaluate($opcao, $extras) : $opcao;
    }
}
