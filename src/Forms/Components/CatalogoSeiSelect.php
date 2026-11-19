<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Support\CatalogosDoSei;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Select com as opções de um catálogo do SEI (em cache pelo conector). Fora do ar ou sem a operação liberada, o campo
 * fica sem opções e o hint diz o porquê; o formulário continua abrindo.
 *
 * ```php
 * SerieSeiSelect::make('id_serie')->conexao('homologacao')->unidade(fn (Get $get) => $get('id_unidade'));
 * ```
 */
abstract class CatalogoSeiSelect extends Select
{
    protected string|Closure|null $conexao = null;

    protected string|Closure|null $unidade = null;

    /** @var array{0: array<string, string>, 1: ?string}|null opções e erro, uma vez por request */
    private ?array $carregado = null;

    private ?string $chaveCarregada = null;

    /** Chave em CatalogosDoSei::CATALOGOS. */
    abstract protected function catalogo(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(CatalogosDoSei::CATALOGOS[$this->catalogo()]['rotulo']);
        $this->searchable();
        $this->options(fn () => $this->carregar()[0]);
        $this->hint(fn () => $this->carregar()[1]);
        $this->hintColor('danger');
        $this->hintIcon(fn () => $this->carregar()[1] !== null ? 'heroicon-m-exclamation-triangle' : null);
    }

    /** Conexão do config('sei.connections'); padrão: a default. */
    public function conexao(string|Closure|null $conexao): static
    {
        $this->conexao = $conexao;

        return $this;
    }

    /** Unidade da consulta (naUnidade); padrão: a da conexão. */
    public function unidade(string|Closure|null $unidade): static
    {
        $this->unidade = $unidade;

        return $this;
    }

    /**
     * @return array{0: array<string, string>, 1: ?string}
     */
    protected function carregar(): array
    {
        $conexao = $this->evaluate($this->conexao);
        $unidade = $this->evaluate($this->unidade);
        $chave = $conexao.'|'.$unidade;

        if ($this->carregado !== null && $this->chaveCarregada === $chave) {
            return $this->carregado;
        }

        $this->chaveCarregada = $chave;

        return $this->carregado = $this->buscar($conexao ?: null, filled($unidade) ? (string) $unidade : null);
    }

    /**
     * @return array{0: array<string, string>, 1: ?string}
     */
    private function buscar(?string $conexao, ?string $unidade): array
    {
        try {
            $cliente = app(SeiManager::class)->connection($conexao);
            $cliente = $unidade !== null ? $cliente->naUnidade($unidade) : $cliente;
            $operacao = CatalogosDoSei::CATALOGOS[$this->catalogo()]['operacao'];

            if (! $cliente->config()->permite($operacao)) {
                return [[], "{$operacao} não está liberada na conexão do SEI."];
            }

            return [CatalogosDoSei::opcoes($cliente->catalogos(), $this->catalogo(), $unidade), null];
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            report($e);

            return [[], 'SEI indisponível: '.Erros::titulo($e).'.'];
        }
    }
}
