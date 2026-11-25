<?php

namespace MonteiroTI\SeiFilament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Forms\Components\HipoteseLegalSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\MarcadorSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\SerieSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\TipoConferenciaSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\TipoPrioridadeSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\TipoProcessoSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\CatalogosDoSei;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Parâmetros da conexão (sei.connections.*.parametros, lidos com Sei::parametro('series.oficio')): os IDs do SEI que
 * o sistema usa, conferidos contra os catálogos (o mesmo do sei:parametros:validar) e editáveis no banco.
 */
class ParametrosSei extends Page
{
    /** Grupos conferidos contra um catálogo, com o select da edição. */
    public const GRUPOS = [
        'unidades' => UnidadeSeiSelect::class,
        'tipos_processo' => TipoProcessoSeiSelect::class,
        'series' => SerieSeiSelect::class,
        'hipoteses_legais' => HipoteseLegalSeiSelect::class,
        'tipos_conferencia' => TipoConferenciaSeiSelect::class,
        'tipos_prioridade' => TipoPrioridadeSeiSelect::class,
        'marcadores' => MarcadorSeiSelect::class,
    ];

    protected static string $view = 'sei-filament::pages.parametros-sei';

    protected static ?string $slug = 'sei-parametros';

    protected static ?string $title = 'Parâmetros do SEI';

    protected static ?string $navigationLabel = 'Parâmetros';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 15;

    public ?string $conexao = null;

    public static function canAccess(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeConfigurar(Filament::auth()->user()) ?? false;
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
    }

    /** @return array<string, string> */
    #[Computed]
    public function conexoes(): array
    {
        return ConexoesNoConfig::nomes();
    }

    #[Computed]
    public function registro(): ?ConexaoSei
    {
        return $this->conexaoEscolhida() !== null
            ? ConexaoSei::query()->where('nome', $this->conexaoEscolhida())->where('ativo', true)->first()
            : null;
    }

    /**
     * Cada parâmetro com o nome no SEI e a situação.
     *
     * @return array{linhas: list<array{grupo: string, chave: string, id: string, nome: ?string, situacao: string, origem: string}>, erros: list<string>}
     */
    #[Computed]
    public function conferencia(): array
    {
        if (($nome = $this->conexaoEscolhida()) === null) {
            return ['linhas' => [], 'erros' => []];
        }

        $manager = app(SeiManager::class);
        $parametros = (array) ($manager->connectionConfig($nome)['parametros'] ?? []);
        $doBanco = Arr::dot($this->registro?->parametros ?? []);
        $linhas = [];
        $erros = [];

        foreach ($parametros as $grupo => $valores) {
            if (! is_array($valores) || $valores === []) {
                continue;
            }

            $itens = null;
            if (isset(self::GRUPOS[$grupo])) {
                try {
                    $itens = CatalogosDoSei::opcoes($manager->connection($nome)->catalogos(), (string) $grupo);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    $erros[] = CatalogosDoSei::CATALOGOS[$grupo]['rotulo'].': '.Erros::titulo($e).'.';
                }
            }

            foreach (Arr::dot($valores) as $chave => $id) {
                $id = is_scalar($id) ? (string) $id : '';
                $linhas[] = [
                    'grupo' => (string) $grupo,
                    'chave' => (string) $chave,
                    'id' => $id,
                    'nome' => $itens[$id] ?? null,
                    'situacao' => match (true) {
                        ! isset(self::GRUPOS[$grupo]) => 'sem_catalogo',
                        $itens === null => 'nao_conferido',
                        isset($itens[$id]) => 'ok',
                        default => 'nao_encontrado',
                    },
                    'origem' => array_key_exists("{$grupo}.{$chave}", $doBanco) ? 'banco' : 'config',
                ];
            }
        }

        return ['linhas' => $linhas, 'erros' => $erros];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editar')
                ->label('Editar parâmetros')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->registro !== null)
                ->slideOver()
                ->modalDescription('Os parâmetros salvos aqui valem por cima dos do config/sei.php, um a um. A chave é o nome usado no código: Sei::parametro(\'series.oficio\').')
                ->fillForm(fn () => collect(self::GRUPOS)->mapWithKeys(fn ($select, $grupo) => [
                    $grupo => collect($this->registro?->parametros[$grupo] ?? [])
                        ->map(fn ($id, $chave) => ['chave' => (string) $chave, 'id' => (string) $id])
                        ->values()
                        ->all(),
                ])->all())
                ->form(fn () => collect(self::GRUPOS)->map(fn (string $select, string $grupo) => Forms\Components\Repeater::make($grupo)
                    ->label(CatalogosDoSei::CATALOGOS[$grupo]['rotulo'])
                    ->schema([
                        Forms\Components\TextInput::make('chave')
                            ->required()
                            ->regex('/^[a-z0-9_]+$/')
                            ->validationMessages(['regex' => 'Só letras minúsculas, números e _.'])
                            ->distinct()
                            ->placeholder('oficio'),
                        $select::make('id')->label('No SEI')->conexao($this->conexaoEscolhida())->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->collapsible()
                    ->addActionLabel('Adicionar'))->values()->all())
                ->action(function (array $data) {
                    $parametros = [];
                    foreach (self::GRUPOS as $grupo => $select) {
                        foreach ($data[$grupo] ?? [] as $linha) {
                            $parametros[$grupo][Str::snake((string) $linha['chave'])] = (string) $linha['id'];
                        }
                    }

                    $this->registro?->update(['parametros' => $parametros ?: null]);
                    unset($this->conferencia, $this->registro);
                    Notification::make()->success()->title('Parâmetros salvos')->send();
                }),
        ];
    }

    private function conexaoEscolhida(): ?string
    {
        return $this->conexao !== null && array_key_exists($this->conexao, $this->conexoes) ? $this->conexao : null;
    }
}
