<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Sip\Dto\Usuario;
use MonteiroTi\Sei\Sip\Enum\OperacaoUsuario;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Rotulos;
use Throwable;

/**
 * Cadastra, atualiza, desativa, reativa ou exclui o usuário do sistema no SIP (replicarUsuario), para ele entrar no
 * SEI. Na tabela de usuários do sistema:
 *
 * ```php
 * ReplicarUsuarioSipAction::tabela(dados: fn (User $record) => [
 *     'id_origem' => $record->id, 'sigla' => $record->login, 'nome' => $record->name, 'cpf' => $record->cpf, 'email' => $record->email,
 * ]);
 * ```
 */
final class ReplicarUsuarioSipAction
{
    use FabricasDeAcao;

    public const NOME = 'replicarUsuarioSip';

    /**
     * @param  (Closure(Model): array<string, mixed>)|null  $dados  id_origem, id_orgao, sigla, nome, nome_social, cpf, email
     * @param  (Closure(Model): ?string)|string|null  $conexao  conexão SIP; padrão: a default
     * @param  bool  $considerarOrgao  o mesmo IdOrigem em vários órgãos (o SIP usa IdOrgao junto)
     */
    protected static function configurar(MountableAction $acao, ?Closure $dados = null, Closure|string|null $conexao = null, bool $considerarOrgao = false): MountableAction
    {
        return $acao
            ->label('Replicar no SIP')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->modalHeading('Replicar usuário no SIP')
            ->modalSubmitActionLabel('Replicar')
            ->fillForm(fn (?Model $record) => [
                'operacao' => OperacaoUsuario::Cadastrar->value,
                ...($dados !== null ? (array) self::avaliar($acao, $dados, ['record' => $record]) : []),
            ])
            ->form(self::campos())
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $conexao, $considerarOrgao) {
                static::replicar($action, $data, static::avaliar($acao, $conexao, ['record' => $record]) ?: null, $considerarOrgao);
            });
    }

    /**
     * Formulário do usuário no SIP, também usado na tela do SIP.
     *
     * @return list<Forms\Components\Component>
     */
    public static function campos(): array
    {
        return [
            Forms\Components\Select::make('operacao')
                ->label('Operação')
                ->options(Rotulos::opcoes(OperacaoUsuario::class))
                ->selectablePlaceholder(false)
                ->required(),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('id_origem')
                    ->label('ID no sistema de origem')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('id_orgao')
                    ->label('ID do órgão no SIP')
                    ->maxLength(30),
                Forms\Components\TextInput::make('sigla')
                    ->label('Sigla (login)')
                    ->maxLength(100),
                Forms\Components\TextInput::make('nome')
                    ->maxLength(100),
                Forms\Components\TextInput::make('nome_social')
                    ->label('Nome social')
                    ->maxLength(100),
                Forms\Components\TextInput::make('cpf')
                    ->label('CPF')
                    ->maxLength(14),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(100),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function replicar(MountableAction $action, array $data, ?string $conexao = null, bool $considerarOrgao = false): void
    {
        $operacao = OperacaoUsuario::from((string) $data['operacao']);
        $texto = fn (string $campo) => filled($data[$campo] ?? null) ? trim((string) $data[$campo]) : null;

        try {
            app(SeiManager::class)->sip($conexao)->replicarUsuario([new Usuario(
                $operacao,
                (string) $texto('id_origem'),
                $texto('id_orgao'),
                $texto('sigla'),
                $texto('nome'),
                $texto('nome_social'),
                ($cpf = preg_replace('/\D/', '', (string) $texto('cpf'))) !== '' ? $cpf : null,
                $texto('email'),
            )], $considerarOrgao);
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            Erros::notificar($e, 'Usuário não replicado no SIP');
            $action->halt();
        }

        Notification::make()->success()->title('Usuário '.($texto('sigla') ?? $texto('id_origem')).': '.mb_strtolower((string) Rotulos::de($operacao)).' no SIP')->send();
    }
}
