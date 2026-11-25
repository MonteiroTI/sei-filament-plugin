<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Jobs\GerarProcessoSeiJob;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Forms\Components\HipoteseLegalSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\TipoProcessoSeiSelect;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\ProcessoDoRegistro;
use MonteiroTI\SeiFilament\Support\Rotulos;
use Throwable;

/**
 * Gera o processo no SEI para o registro da tela (Sei::integracao()->gerarProcesso): uma vez só por registro e
 * conexão, com o vínculo gravado em sei_processos. O model precisa do HasSeiProcesso do conector.
 *
 * ```php
 * GerarProcessoSeiAction::make(
 *     preencher: fn (Ferias $record) => ['id_tipo_procedimento' => Sei::parametro('tipos_processo.ferias'), 'especificacao' => "Férias de {$record->servidor->nome}"],
 *     documentos: fn (Ferias $record) => [Documento::gerado(Sei::parametro('series.requerimento'), view('ferias.requerimento', compact('record'))->render())],
 * );
 * ```
 */
final class GerarProcessoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'gerarProcessoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao  padrão: a default
     * @param  (Closure(Model): ?string)|string|null  $unidade  unidade geradora; padrão: a da conexão
     * @param  (Closure(Model): array<string, mixed>)|null  $preencher  valores iniciais do formulário
     * @param  (Closure(Model, array<string, mixed>): list<Documento>)|null  $documentos  documentos gerados junto com o processo
     * @param  (Closure(Model): ?string)|string|null  $chave  idempotência; padrão: um processo por registro e conexão
     * @param  bool  $naFila  gera pelo GerarProcessoSeiJob do conector (a unidade é a da conexão)
     * @param  (Closure(SeiProcesso, Model): mixed)|null  $depois
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $conexao = null,
        Closure|string|null $unidade = null,
        ?Closure $preencher = null,
        ?Closure $documentos = null,
        Closure|string|null $chave = null,
        bool $naFila = false,
        ?Closure $depois = null,
    ): MountableAction {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;
        $unidadeDo = fn (?Model $record): ?string => self::avaliar($acao, $unidade, ['record' => $record]) ?: null;

        return $acao
            ->label('Gerar processo no SEI')
            ->icon('heroicon-o-folder-plus')
            ->modalHeading('Gerar processo no SEI')
            ->modalSubmitActionLabel('Gerar processo')
            // Com a chave padrão o conector não gera um segundo processo para o registro: a ação some.
            ->hidden(fn (?Model $record) => $record === null || ($chave === null && ProcessoDoRegistro::de($record, $conexaoDo($record) ?? app(SeiManager::class)->getDefaultConnection()) !== null))
            ->fillForm(fn (?Model $record) => [
                'nivel_acesso' => NivelAcesso::Publico->value,
                'interessados' => [],
                ...($preencher !== null ? (array) self::avaliar($acao, $preencher, ['record' => $record]) : []),
            ])
            ->form(fn (?Model $record) => [
                TipoProcessoSeiSelect::make('id_tipo_procedimento')
                    ->conexao($conexaoDo($record))
                    ->unidade($unidadeDo($record))
                    ->required(),
                Forms\Components\TextInput::make('especificacao')
                    ->label('Especificação')
                    ->maxLength(100)
                    ->helperText('Resumo que aparece na árvore e nas pesquisas do SEI.'),
                Forms\Components\Repeater::make('interessados')
                    ->schema([
                        Forms\Components\TextInput::make('nome')->required()->maxLength(250),
                        Forms\Components\TextInput::make('documento')
                            ->label('CPF ou CNPJ')
                            ->maxLength(18)
                            ->helperText('Opcional. Com ele o SEI acha o contato já cadastrado.'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar interessado'),
                ...self::camposDeAcesso($conexaoDo($record)),
                Forms\Components\Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(2),
            ])
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $conexaoDo, $unidadeDo, $documentos, $chave, $naFila, $depois) {
                $conexaoEscolhida = $conexaoDo($record);
                $procedimento = new Procedimento(
                    idTipoProcedimento: (string) $data['id_tipo_procedimento'],
                    especificacao: $data['especificacao'] ?? null ?: null,
                    interessados: static::interessados($data['interessados'] ?? []),
                    observacao: $data['observacao'] ?? null ?: null,
                    nivelAcesso: NivelAcesso::from((int) $data['nivel_acesso']),
                    idHipoteseLegal: $data['id_hipotese_legal'] ?? null ?: null,
                );
                $docs = $documentos !== null ? (array) static::avaliar($acao, $documentos, ['record' => $record, 'data' => $data]) : [];
                $chaveEscolhida = static::avaliar($acao, $chave, ['record' => $record]) ?: null;

                try {
                    if ($naFila) {
                        GerarProcessoSeiJob::dispatch($record, $procedimento, array_values($docs), null, $conexaoEscolhida, $chaveEscolhida);
                        Notification::make()->success()->title('Geração do processo enviada para a fila')->body('O processo aparece aqui quando o SEI responder.')->send();

                        return;
                    }

                    $integracao = app(SeiManager::class)->integracao($conexaoEscolhida);
                    $integracao = ($u = $unidadeDo($record)) !== null ? $integracao->naUnidade($u) : $integracao;
                    $processo = $integracao->gerarProcesso($record, $procedimento, array_values($docs), null, $chaveEscolhida);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Processo não gerado');
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title("Processo {$processo->protocolo_formatado} gerado no SEI")
                    ->actions(array_filter([
                        $processo->link_acesso ? NotificationAction::make('abrir')->label('Abrir no SEI')->url($processo->link_acesso, shouldOpenInNewTab: true) : null,
                    ]))
                    ->send();

                if ($depois !== null) {
                    $acao->evaluate($depois, ['processo' => $processo, 'record' => $record]);
                }
            });
    }

    /**
     * Nível de acesso e hipótese legal (exigida fora do público), também usados ao incluir documento.
     *
     * @return list<Forms\Components\Component>
     */
    public static function camposDeAcesso(?string $conexao): array
    {
        return [
            Forms\Components\Radio::make('nivel_acesso')
                ->label('Nível de acesso')
                ->options(Rotulos::opcoes(NivelAcesso::class))
                ->inline()
                ->live()
                ->required(),
            HipoteseLegalSeiSelect::make('id_hipotese_legal')
                ->conexao($conexao)
                ->visible(fn (Get $get) => (int) $get('nivel_acesso') !== NivelAcesso::Publico->value)
                ->required(fn (Get $get) => (int) $get('nivel_acesso') !== NivelAcesso::Publico->value),
        ];
    }

    /**
     * @param  array<array-key, array{nome?: string|null, documento?: string|null}>  $linhas
     * @return list<ContatoRef>
     */
    public static function interessados(array $linhas): array
    {
        $contatos = [];
        foreach ($linhas as $linha) {
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }

            // CNPJ alfanumérico: tira só a máscara, mantém as letras.
            $documento = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($linha['documento'] ?? '')));
            $contatos[] = match (true) {
                strlen($documento) === 11 && ctype_digit($documento) => ContatoRef::cpf($documento, $nome),
                strlen($documento) === 14 => ContatoRef::cnpj($documento, $nome),
                default => ContatoRef::nome($nome),
            };
        }

        return $contatos;
    }
}
