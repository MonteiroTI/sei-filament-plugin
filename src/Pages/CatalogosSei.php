<?php

namespace MonteiroTI\SeiFilament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\SeiClient;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\CacheDosCatalogos;
use MonteiroTI\SeiFilament\Support\CatalogosDoSei;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Consulta dos catálogos do SEI por conexão e unidade: os IDs que o sistema precisa para gerar processos e documentos.
 */
class CatalogosSei extends Page
{
    public const PERMISSAO = 'consultar_catalogos:sei';

    /** Linhas mostradas de uma vez; a busca acha as outras. */
    public const LIMITE = 500;

    protected static string $view = 'sei-filament::pages.catalogos-sei';

    protected static ?string $slug = 'sei-catalogos';

    protected static ?string $title = 'Catálogos do SEI';

    protected static ?string $navigationLabel = 'Catálogos';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 20;

    public ?string $conexao = null;

    public ?string $unidade = null;

    public string $catalogo = 'unidades';

    public string $busca = '';

    public static function canAccess(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeConsultarCatalogos(Filament::auth()->user()) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public function mount(): void
    {
        $this->conexao = app(SeiManager::class)->getDefaultConnection();
        $this->unidade = $this->unidadePadrao();
    }

    public function updatedConexao(): void
    {
        $this->unidade = $this->unidadePadrao();
        $this->busca = '';
    }

    public function updatedCatalogo(): void
    {
        $this->busca = '';
    }

    public function escolherCatalogo(string $catalogo): void
    {
        if (isset(CatalogosDoSei::CATALOGOS[$catalogo])) {
            $this->catalogo = $catalogo;
            $this->busca = '';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limparCache')
                ->label('Limpar cache')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Os catálogos de todas as conexões voltam a ser buscados no SEI na próxima consulta.')
                ->action(function () {
                    CacheDosCatalogos::limpar();
                    unset($this->resultado, $this->unidades);
                    Notification::make()->success()->title('Cache dos catálogos limpo')->send();
                }),
        ];
    }

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

    /**
     * @return array{linhas: list<array<string, string>>, total: int, erro: ?string}
     */
    #[Computed]
    public function resultado(): array
    {
        $operacao = CatalogosDoSei::CATALOGOS[$this->catalogo]['operacao'];

        try {
            $cliente = $this->cliente();

            if (! $cliente->config()->permite($operacao)) {
                return ['linhas' => [], 'total' => 0, 'erro' => "A operação {$operacao} não está liberada nesta conexão."];
            }

            $linhas = CatalogosDoSei::linhas($cliente->catalogos(), $this->catalogo, filled($this->unidade) ? (string) $this->unidade : null);
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return ['linhas' => [], 'total' => 0, 'erro' => Erros::titulo($e).': '.Erros::mensagem($e)];
        }

        $busca = Str::lower(Str::ascii(trim($this->busca)));
        if ($busca !== '') {
            $linhas = array_values(array_filter($linhas, fn (array $linha) => str_contains(Str::lower(Str::ascii(implode(' ', $linha))), $busca)));
        }

        return ['linhas' => array_slice($linhas, 0, self::LIMITE), 'total' => count($linhas), 'erro' => null];
    }

    /** @return array<string, string> */
    public function colunas(): array
    {
        return CatalogosDoSei::CATALOGOS[$this->catalogo]['colunas'];
    }

    protected function cliente(): SeiClient
    {
        $conexao = $this->conexao !== null && array_key_exists($this->conexao, $this->conexoes) ? $this->conexao : null;
        $cliente = app(SeiManager::class)->connection($conexao);

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
