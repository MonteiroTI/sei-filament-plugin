<?php

namespace MonteiroTI\SeiFilament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use MonteiroTi\Sei\Dto\Contato;
use MonteiroTi\Sei\Enum\OperacaoContato;
use MonteiroTi\Sei\Options\FiltroContatos;
use MonteiroTI\SeiFilament\Concerns\EscolheConexaoEUnidade;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Contatos do SEI (interessados, remetentes, destinatários): busca, edição dos dados de contato, desativação e
 * reativação. Tem dados pessoais: a tela vem desligada e pede permissão própria.
 */
class ContatosSei extends Page
{
    use EscolheConexaoEUnidade;

    public const PERMISSAO = 'gerenciar_contatos:sei';

    public const POR_PAGINA = 25;

    /** Campos que a tela edita; o resto do contato vai como o SEI devolveu. */
    public const CAMPOS = [
        'nome' => 'Nome',
        'nomeSocial' => 'Nome social',
        'sigla' => 'Sigla',
        'email' => 'E-mail',
        'telefoneFixo' => 'Telefone fixo',
        'telefoneCelular' => 'Celular',
        'endereco' => 'Endereço',
        'complemento' => 'Complemento',
        'bairro' => 'Bairro',
        'cep' => 'CEP',
        'observacao' => 'Observação',
    ];

    protected static string $view = 'sei-filament::pages.contatos-sei';

    protected static ?string $slug = 'sei-contatos';

    protected static ?string $title = 'Contatos do SEI';

    protected static ?string $navigationLabel = 'Contatos';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 35;

    /** @var array<string, string|null> */
    public array $filtro = ['nome' => null, 'sigla' => null, 'cpf' => null, 'cnpj' => null, 'matricula' => null];

    public int $pagina = 1;

    public bool $buscou = false;

    public static function canAccess(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeGerenciarContatos(Filament::auth()->user()) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public function mount(): void
    {
        $this->iniciarConexao();
    }

    protected function aoTrocarConexao(): void
    {
        $this->buscou = false;
    }

    public function buscar(): void
    {
        $this->buscou = true;
        $this->pagina = 1;
        unset($this->resultado);
    }

    public function irParaPagina(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
        unset($this->resultado);
    }

    /**
     * @return array{contatos: list<Contato>, erro: ?string}
     */
    #[Computed]
    public function resultado(): array
    {
        if (! $this->buscou) {
            return ['contatos' => [], 'erro' => null];
        }

        try {
            return ['contatos' => $this->cliente()->contatos()->listar($this->filtro(), $this->pagina, self::POR_PAGINA), 'erro' => null];
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return ['contatos' => [], 'erro' => Erros::titulo($e).': '.Erros::mensagem($e)];
        }
    }

    public function editarAction(): Action
    {
        return Action::make('editar')
            ->label('Editar contato')
            ->modalHeading(fn (array $arguments) => 'Editar contato '.$arguments['id'])
            ->fillForm(function (array $arguments) {
                $contato = $this->contato((string) $arguments['id']);

                return collect(self::CAMPOS)->mapWithKeys(fn ($rotulo, $campo) => [$campo => $contato?->{$campo}])->all();
            })
            ->form([
                Forms\Components\Grid::make(2)->schema(collect(self::CAMPOS)->map(fn (string $rotulo, string $campo) => match ($campo) {
                    'observacao' => Forms\Components\Textarea::make($campo)->label($rotulo)->rows(2)->columnSpanFull(),
                    'email' => Forms\Components\TextInput::make($campo)->label($rotulo)->email()->maxLength(100),
                    'nome' => Forms\Components\TextInput::make($campo)->label($rotulo)->required()->maxLength(250),
                    default => Forms\Components\TextInput::make($campo)->label($rotulo)->maxLength(250),
                })->values()->all()),
            ])
            ->action(function (array $arguments, array $data) {
                $this->atualizar((string) $arguments['id'], OperacaoContato::Atualizar, array_map(fn ($v) => filled($v) ? trim((string) $v) : null, $data), 'Contato atualizado');
            });
    }

    public function desativarAction(): Action
    {
        return Action::make('desativar')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(fn (array $arguments) => 'Desativar o contato '.$arguments['id'].'?')
            ->action(fn (array $arguments) => $this->atualizar((string) $arguments['id'], OperacaoContato::Desativar, [], 'Contato desativado'));
    }

    public function reativarAction(): Action
    {
        return Action::make('reativar')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Reativar o contato '.$arguments['id'].'?')
            ->action(fn (array $arguments) => $this->atualizar((string) $arguments['id'], OperacaoContato::Reativar, [], 'Contato reativado'));
    }

    /**
     * O atualizarContatos troca o contato inteiro: parte do registro atual do SEI e muda só os campos da tela.
     *
     * @param  array<string, mixed>  $mudancas
     */
    private function atualizar(string $id, OperacaoContato $operacao, array $mudancas, string $sucesso): void
    {
        try {
            $contato = $this->contato($id) ?? throw new \InvalidArgumentException("Contato {$id} não encontrado no SEI.");
            $novo = new Contato(...[...get_object_vars($contato), ...array_intersect_key($mudancas, self::CAMPOS)]);
            $this->cliente()->contatos()->atualizar($novo->comOperacao($operacao));
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            Erros::notificar($e, 'O SEI recusou a alteração');

            return;
        }

        unset($this->resultado);
        Notification::make()->success()->title($sucesso)->send();
    }

    private function contato(string $id): ?Contato
    {
        return $this->cliente()->contatos()->listar(new FiltroContatos(idContatos: [$id]), 1, 1)[0] ?? null;
    }

    private function filtro(): FiltroContatos
    {
        $f = array_map(fn ($v) => filled($v) ? trim((string) $v) : null, $this->filtro);

        return new FiltroContatos(
            sigla: $f['sigla'] ?? null,
            nome: $f['nome'] ?? null,
            cpf: ($cpf = preg_replace('/\D/', '', (string) ($f['cpf'] ?? ''))) !== '' ? $cpf : null,
            // CNPJ alfanumérico: tira só a máscara.
            cnpj: ($cnpj = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($f['cnpj'] ?? '')))) !== '' ? $cnpj : null,
            matricula: $f['matricula'] ?? null,
        );
    }
}
