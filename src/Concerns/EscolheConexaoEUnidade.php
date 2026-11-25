<?php

namespace MonteiroTI\SeiFilament\Concerns;

use Livewire\Attributes\Computed;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\SeiClient;
use MonteiroTI\SeiFilament\Support\CatalogosDoSei;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Página do plugin que trabalha numa conexão e unidade escolhidas na tela (partial sei-filament::partials.conexao-unidade).
 */
trait EscolheConexaoEUnidade
{
    public ?string $conexao = null;

    public ?string $unidade = null;

    protected function iniciarConexao(): void
    {
        $this->conexao = app(SeiManager::class)->getDefaultConnection();
        $this->unidade = $this->unidadePadrao();
    }

    public function updatedConexao(): void
    {
        $this->unidade = $this->unidadePadrao();
        $this->aoTrocarConexao();
    }

    /** Gancho para limpar o estado da página ao trocar de conexão. */
    protected function aoTrocarConexao(): void {}

    /** @return array<string, string> */
    #[Computed]
    public function conexoes(): array
    {
        return ConexoesNoConfig::nomes();
    }

    /** @return array<string, string> */
    #[Computed]
    public function unidades(): array
    {
        try {
            return CatalogosDoSei::opcoes($this->cliente()->catalogos(), 'unidades');
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return [];
        }
    }

    /** Nome da conexão escolhida, se existir (o valor vem do navegador). */
    protected function conexaoEscolhida(): ?string
    {
        return $this->conexao !== null && array_key_exists($this->conexao, $this->conexoes) ? $this->conexao : null;
    }

    protected function cliente(): SeiClient
    {
        $cliente = app(SeiManager::class)->connection($this->conexaoEscolhida());

        return filled($this->unidade) ? $cliente->naUnidade((string) $this->unidade) : $cliente;
    }

    private function unidadePadrao(): ?string
    {
        try {
            return app(SeiManager::class)->connectionConfig((string) $this->conexao)['id_unidade'] ?? null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
