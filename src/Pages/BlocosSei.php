<?php

namespace MonteiroTI\SeiFilament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use MonteiroTi\Sei\Dto\Bloco;
use MonteiroTi\Sei\Enum\EstadoBloco;
use MonteiroTi\Sei\Enum\TipoBloco;
use MonteiroTI\SeiFilament\Concerns\EscolheConexaoEUnidade;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Rotulos;
use Throwable;

/**
 * Blocos de assinatura, reunião e internos da unidade: consulta pelo número (o SEI não lista os blocos), situação,
 * documentos com as assinaturas e as ações do bloco.
 */
class BlocosSei extends Page
{
    use EscolheConexaoEUnidade;

    public const PERMISSAO = 'gerenciar_blocos:sei';

    protected static string $view = 'sei-filament::pages.blocos-sei';

    protected static ?string $slug = 'sei-blocos';

    protected static ?string $title = 'Blocos do SEI';

    protected static ?string $navigationLabel = 'Blocos';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 25;

    #[Url]
    public string $bloco = '';

    public static function canAccess(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeGerenciarBlocos(Filament::auth()->user()) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public function mount(): void
    {
        // A ação "Incluir em bloco" manda a conexão e a unidade do bloco na URL.
        $this->iniciarConexao();
        $this->conexao = request()->query('conexao') ?: $this->conexao;
        $this->unidade = request()->query('unidade') ?: $this->unidade;
    }

    protected function aoTrocarConexao(): void
    {
        $this->bloco = '';
    }

    public function updatedUnidade(): void
    {
        unset($this->consulta);
    }

    /**
     * @return array{bloco: ?Bloco, erro: ?string}
     */
    #[Computed]
    public function consulta(): array
    {
        if (trim($this->bloco) === '') {
            return ['bloco' => null, 'erro' => null];
        }

        try {
            return ['bloco' => $this->cliente()->blocos()->consultar(trim($this->bloco), retornarProtocolos: true), 'erro' => null];
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return ['bloco' => null, 'erro' => Erros::titulo($e).': '.Erros::mensagem($e)];
        }
    }

    public function consultar(): void
    {
        unset($this->consulta);
    }

    public function retirar(string $protocolo): void
    {
        $bloco = $this->consulta['bloco'];
        if ($bloco === null) {
            return;
        }

        $this->executar(fn () => $bloco->tipo === TipoBloco::Assinatura || $bloco->tipo === null
            ? $this->cliente()->blocos()->retirarDocumento((string) $bloco->idBloco, $protocolo)
            : $this->cliente()->blocos()->retirarProcesso((string) $bloco->idBloco, $protocolo),
            "{$protocolo} retirado do bloco");
    }

    protected function getHeaderActions(): array
    {
        $estado = fn (): ?EstadoBloco => $this->consulta['bloco']?->estado;
        $idBloco = fn (): string => (string) $this->consulta['bloco']?->idBloco;

        return [
            Action::make('novo')
                ->label('Novo bloco')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Radio::make('tipo')
                        ->options(Rotulos::opcoes(TipoBloco::class))
                        ->default(TipoBloco::Assinatura->value)
                        ->inline()
                        ->required(),
                    Forms\Components\TextInput::make('descricao')->label('Descrição')->required()->maxLength(250),
                    UnidadeSeiSelect::make('unidades')->label('Disponibilizar para as unidades')->conexao($this->conexaoEscolhida())->multiple(),
                ])
                ->action(function (array $data) {
                    $this->executar(function () use ($data) {
                        $this->bloco = $this->cliente()->blocos()->gerar(
                            TipoBloco::from($data['tipo']),
                            (string) $data['descricao'],
                            array_values(array_map('strval', (array) ($data['unidades'] ?? []))),
                        );
                    }, fn () => "Bloco {$this->bloco} gerado");
                }),
            $this->acaoDoBloco('disponibilizar', 'Disponibilizar', 'heroicon-o-share', fn () => $this->cliente()->blocos()->disponibilizar($idBloco()))
                ->visible(fn () => $estado() === EstadoBloco::Aberto || $estado() === EstadoBloco::Retornado),
            $this->acaoDoBloco('cancelarDisponibilizacao', 'Cancelar disponibilização', 'heroicon-o-x-mark', fn () => $this->cliente()->blocos()->cancelarDisponibilizacao($idBloco()))
                ->visible(fn () => $estado() === EstadoBloco::Disponibilizado),
            $this->acaoDoBloco('devolver', 'Devolver', 'heroicon-o-arrow-uturn-left', fn () => $this->cliente()->blocos()->devolver($idBloco()))
                ->visible(fn () => $estado() === EstadoBloco::Recebido),
            $this->acaoDoBloco('concluir', 'Concluir', 'heroicon-o-check-circle', fn () => $this->cliente()->blocos()->concluir($idBloco()))
                ->visible(fn () => in_array($estado(), [EstadoBloco::Aberto, EstadoBloco::Retornado], true)),
            $this->acaoDoBloco('reabrir', 'Reabrir', 'heroicon-o-arrow-path', fn () => $this->cliente()->blocos()->reabrir($idBloco()))
                ->visible(fn () => $estado() === EstadoBloco::Concluido),
            $this->acaoDoBloco('excluir', 'Excluir', 'heroicon-o-trash', function () use ($idBloco) {
                $this->cliente()->blocos()->excluir($idBloco());
                $this->bloco = '';
            })
                ->color('danger')
                ->visible(fn () => $estado() !== null),
        ];
    }

    private function acaoDoBloco(string $nome, string $rotulo, string $icone, \Closure $chamada): Action
    {
        return Action::make($nome)
            ->label($rotulo)
            ->icon($icone)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn () => "{$rotulo}: bloco {$this->consulta['bloco']?->idBloco}?")
            ->action(fn () => $this->executar($chamada, "Bloco: {$rotulo} feito"));
    }

    private function executar(\Closure $chamada, string|\Closure $sucesso): void
    {
        try {
            $chamada();
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            Erros::notificar($e);

            return;
        } finally {
            unset($this->consulta);
        }

        Notification::make()->success()->title(is_string($sucesso) ? $sucesso : $sucesso())->send();
    }
}
