<?php

namespace MonteiroTI\SeiFilament\Pages;

use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Sip\Dto\FiltroPermissao;
use MonteiroTi\Sei\Sip\Dto\Permissao;
use MonteiroTi\Sei\Sip\Enum\OperacaoPermissao;
use MonteiroTI\SeiFilament\Actions\ReplicarUsuarioSipAction;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Usuários e permissões no SIP: consulta das permissões do sistema, concessão e retirada, e replicação de usuários.
 */
class SipSei extends Page
{
    public const PERMISSAO = 'administrar_sip:sei';

    protected static string $view = 'sei-filament::pages.sip-sei';

    protected static ?string $slug = 'sei-sip';

    protected static ?string $title = 'Usuários e permissões no SIP';

    protected static ?string $navigationLabel = 'SIP';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 60;

    public ?string $conexao = null;

    /** @var array<string, string|null> */
    public array $filtro = ['id_usuario' => null, 'id_origem_usuario' => null, 'id_unidade' => null, 'id_perfil' => null];

    public bool $buscou = false;

    public static function canAccess(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeAdministrarSip(Filament::auth()->user()) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public function mount(): void
    {
        $this->conexao = array_key_first($this->conexoes);
    }

    /** @return array<string, string> */
    #[Computed]
    public function conexoes(): array
    {
        return ConexoesNoConfig::nomesSip();
    }

    public function buscar(): void
    {
        $this->buscou = true;
        unset($this->resultado);
    }

    /**
     * Sem filtro o SIP devolve todas as permissões do sistema: só busca quando pedem.
     *
     * @return array{permissoes: list<Permissao>, erro: ?string}
     */
    #[Computed]
    public function resultado(): array
    {
        if (! $this->buscou || $this->conexaoEscolhida() === null) {
            return ['permissoes' => [], 'erro' => null];
        }

        $f = array_map(fn ($v) => filled($v) ? trim((string) $v) : null, $this->filtro);

        try {
            return ['permissoes' => app(SeiManager::class)->sip($this->conexaoEscolhida())->listarPermissao(new FiltroPermissao(
                idUsuario: $f['id_usuario'] ?? null,
                idOrigemUsuario: $f['id_origem_usuario'] ?? null,
                idUnidade: $f['id_unidade'] ?? null,
                idPerfil: $f['id_perfil'] ?? null,
            )), 'erro' => null];
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return ['permissoes' => [], 'erro' => Erros::titulo($e).': '.Erros::mensagem($e)];
        }
    }

    public function retirar(int $indice): void
    {
        $permissao = $this->resultado['permissoes'][$indice] ?? null;
        if ($permissao === null) {
            return;
        }

        $this->replicarPermissao(new Permissao(
            OperacaoPermissao::Excluir,
            $permissao->idSistema,
            $permissao->idOrgaoUsuario,
            $permissao->idUsuario,
            $permissao->idOrigemUsuario,
            $permissao->idOrgaoUnidade,
            $permissao->idUnidade,
            $permissao->idOrigemUnidade,
            $permissao->idPerfil,
        ), 'Permissão retirada');
    }

    protected function getHeaderActions(): array
    {
        return [
            ReplicarUsuarioSipAction::make(conexao: fn () => $this->conexaoEscolhida())
                ->label('Replicar usuário')
                ->visible(fn () => $this->conexaoEscolhida() !== null),
            Action::make('conceder')
                ->label('Conceder permissão')
                ->icon('heroicon-o-plus')
                ->visible(fn () => $this->conexaoEscolhida() !== null)
                ->fillForm(['data_inicial' => now()->toDateString()])
                ->form([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('id_origem_usuario')->label('Usuário (ID de origem)')->required(),
                        Forms\Components\TextInput::make('id_orgao_usuario')->label('Órgão do usuário (ID)'),
                        Forms\Components\TextInput::make('id_unidade')->label('Unidade (ID no SIP)')->required(),
                        Forms\Components\TextInput::make('id_perfil')->label('Perfil (ID)')->required(),
                        Forms\Components\DatePicker::make('data_inicial')->label('Início')->required(),
                        Forms\Components\DatePicker::make('data_final')->label('Fim')->afterOrEqual('data_inicial'),
                        Forms\Components\Toggle::make('subunidades')->label('Vale para as subunidades'),
                    ]),
                ])
                ->action(fn (array $data) => $this->replicarPermissao(new Permissao(
                    operacao: OperacaoPermissao::Atualizar,
                    idOrgaoUsuario: $data['id_orgao_usuario'] ?? null ?: null,
                    idOrigemUsuario: (string) $data['id_origem_usuario'],
                    idUnidade: (string) $data['id_unidade'],
                    idPerfil: (string) $data['id_perfil'],
                    dataInicial: new DateTimeImmutable((string) $data['data_inicial']),
                    dataFinal: filled($data['data_final'] ?? null) ? new DateTimeImmutable((string) $data['data_final']) : null,
                    subunidades: (bool) ($data['subunidades'] ?? false),
                ), 'Permissão concedida')),
        ];
    }

    private function replicarPermissao(Permissao $permissao, string $sucesso): void
    {
        try {
            app(SeiManager::class)->sip($this->conexaoEscolhida())->replicarPermissao($permissao);
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            Erros::notificar($e, 'O SIP recusou a permissão');

            return;
        }

        unset($this->resultado);
        Notification::make()->success()->title($sucesso)->send();
    }

    private function conexaoEscolhida(): ?string
    {
        return $this->conexao !== null && array_key_exists($this->conexao, $this->conexoes) ? $this->conexao : null;
    }
}
