<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use MonteiroTi\Sei\Dto\Anexo;
use MonteiroTi\Sei\Dto\ProcedimentoResumido;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\ManifestacaoOuvidoria;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Registra uma manifestação de ouvidoria (registrarOuvidoria): o SEI gera o processo no órgão. Para o atendimento
 * registrar o que chegou por telefone, e-mail ou balcão.
 *
 * ```php
 * RegistrarOuvidoriaSeiAction::make(orgao: Sei::parametro('orgaos.principal'), preencher: fn (Atendimento $record) => [...]);
 * ```
 */
final class RegistrarOuvidoriaSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'registrarOuvidoriaSei';

    /**
     * @param  (Closure(Model|null): ?string)|string|null  $orgao  ID do órgão no SEI; sem ele, o formulário pede
     * @param  (Closure(Model|null): ?string)|string|null  $conexao
     * @param  (Closure(Model|null): array<string, mixed>)|null  $preencher  valores iniciais (nome, email, cpf, mensagem...)
     * @param  (Closure(ProcedimentoResumido, Model|null): mixed)|null  $depois
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $orgao = null,
        Closure|string|null $conexao = null,
        ?Closure $preencher = null,
        ?Closure $depois = null,
    ): MountableAction {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;

        return $acao
            ->label('Registrar na ouvidoria')
            ->icon('heroicon-o-megaphone')
            ->modalHeading('Registrar manifestação na ouvidoria do SEI')
            ->modalSubmitActionLabel('Registrar')
            ->fillForm(fn (?Model $record = null) => [
                'id_orgao' => self::avaliar($acao, $orgao, ['record' => $record]),
                'anonimo' => false,
                'sigilo' => false,
                'retorno' => true,
                ...($preencher !== null ? (array) self::avaliar($acao, $preencher, ['record' => $record]) : []),
            ])
            ->form(fn (?Model $record = null) => [
                Forms\Components\TextInput::make('id_orgao')
                    ->label('Órgão (ID no SEI)')
                    ->required()
                    ->hidden(fn () => self::avaliar($acao, $orgao, ['record' => $record]) !== null),
                Forms\Components\Select::make('id_tipo_procedimento')
                    ->label('Tipo de manifestação')
                    ->options(fn () => self::tipos($conexaoDo($record)))
                    ->searchable()
                    ->required(),
                Forms\Components\Toggle::make('anonimo')->label('Anônima')->live(),
                Forms\Components\Grid::make(2)
                    ->hidden(fn (Get $get) => (bool) $get('anonimo'))
                    ->schema([
                        Forms\Components\TextInput::make('nome')->required(fn (Get $get) => ! $get('anonimo'))->maxLength(100),
                        Forms\Components\TextInput::make('email')->label('E-mail')->email()->required(fn (Get $get) => ! $get('anonimo'))->maxLength(100),
                        // O SEI pede o CPF ou o RG com o órgão expedidor.
                        Forms\Components\TextInput::make('cpf')->label('CPF')->maxLength(14)
                            ->required(fn (Get $get) => ! $get('anonimo') && blank($get('rg'))),
                        Forms\Components\Grid::make(2)->columnSpan(1)->schema([
                            Forms\Components\TextInput::make('rg')->label('RG')->maxLength(20)->live(onBlur: true),
                            Forms\Components\TextInput::make('orgao_expedidor')->label('Órgão expedidor')->maxLength(20)
                                ->required(fn (Get $get) => filled($get('rg'))),
                        ]),
                        Forms\Components\TextInput::make('telefone')->maxLength(30),
                        Forms\Components\TextInput::make('cidade')->maxLength(100),
                        Forms\Components\TextInput::make('estado')->label('UF')->maxLength(2),
                    ]),
                Forms\Components\Textarea::make('mensagem')->required()->rows(5),
                Forms\Components\TextInput::make('processos')->label('Processos relacionados')->maxLength(250),
                Forms\Components\FileUpload::make('anexos')->multiple()->storeFiles(false)->maxSize(10240),
                Forms\Components\Toggle::make('sigilo')->label('Pede sigilo dos dados'),
                Forms\Components\Toggle::make('retorno')->label('Quer receber retorno'),
            ])
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $orgao, $conexaoDo, $depois) {
                $texto = fn (string $campo) => filled($data[$campo] ?? null) ? trim((string) $data[$campo]) : null;
                $anonimo = (bool) ($data['anonimo'] ?? false);

                try {
                    $anexos = array_values(array_map(
                        fn (UploadedFile $arquivo) => Anexo::fromPath((string) $arquivo->getRealPath(), $arquivo->getClientOriginalName()),
                        array_filter((array) ($data['anexos'] ?? []), fn ($a) => $a instanceof UploadedFile),
                    ));

                    $processo = app(SeiManager::class)->connection($conexaoDo($record))->ouvidoria()->registrar(new ManifestacaoOuvidoria(
                        // Campo escondido não vem no formulário: o órgão da opção vale direto ("0" inclusive).
                        idOrgao: (string) (static::avaliar($acao, $orgao, ['record' => $record]) ?? $texto('id_orgao')),
                        idTipoProcedimento: (string) $texto('id_tipo_procedimento'),
                        nome: $anonimo ? 'Anônimo' : (string) $texto('nome'),
                        email: $anonimo ? '' : (string) $texto('email'),
                        mensagem: (string) $texto('mensagem'),
                        cpf: $anonimo ? null : (($cpf = preg_replace('/\D/', '', (string) $texto('cpf'))) !== '' ? $cpf : null),
                        rg: $anonimo ? null : $texto('rg'),
                        orgaoExpedidor: $anonimo ? null : $texto('orgao_expedidor'),
                        telefone: $anonimo ? null : $texto('telefone'),
                        estado: $anonimo ? null : $texto('estado'),
                        cidade: $anonimo ? null : $texto('cidade'),
                        processos: $texto('processos'),
                        retorno: ! $anonimo && (bool) ($data['retorno'] ?? false),
                        anonimo: $anonimo,
                        sigilo: (bool) ($data['sigilo'] ?? false),
                        anexos: $anexos,
                    ));
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Manifestação não registrada');
                    $action->halt();
                }

                Notification::make()->success()->title("Manifestação registrada: processo {$processo->procedimentoFormatado}")->persistent()->send();

                if ($depois !== null) {
                    $acao->evaluate($depois, ['processo' => $processo, 'record' => $record]);
                }
            });
    }

    /**
     * @return array<string, string>
     */
    private static function tipos(?string $conexao): array
    {
        try {
            $tipos = app(SeiManager::class)->connection($conexao)->ouvidoria()->tiposProcedimento();
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }

            return [];
        }

        $opcoes = [];
        foreach ($tipos as $tipo) {
            if ($tipo->idTipoProcedimento !== null) {
                $opcoes[$tipo->idTipoProcedimento] = (string) ($tipo->nome ?? $tipo->idTipoProcedimento);
            }
        }

        return $opcoes;
    }
}
