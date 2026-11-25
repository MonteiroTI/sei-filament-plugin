<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use DateTimeImmutable;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Enum\TipoDocumento;
use MonteiroTi\Sei\Laravel\Jobs\IncluirDocumentoSeiJob;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\SeiClient;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Forms\Components\SerieSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\TipoConferenciaSeiSelect;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\ProcessoDoRegistro;
use Throwable;

/**
 * Inclui um documento no processo do registro (Sei::integracao()->incluirDocumento): gerado no editor do SEI (HTML) ou
 * externo (arquivo). O registro é o SeiProcesso ou um model com HasSeiProcesso; sem processo, a ação some.
 *
 * Cada abertura do formulário tem a sua chave de idempotência: clicar duas vezes em "Incluir" não duplica o documento.
 */
final class IncluirDocumentoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'incluirDocumentoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade
     * @param  (Closure(Model): array<string, mixed>)|null  $preencher  valores iniciais (tipo, id_serie, descricao, conteudo...)
     * @param  bool  $naFila  inclui pelo IncluirDocumentoSeiJob (arquivo grande sobe antes, na hora)
     * @param  int  $tamanhoMaximoKb  tamanho máximo do arquivo externo
     * @param  (Closure(SeiDocumento, Model): mixed)|null  $depois
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $conexao = null,
        Closure|string|null $unidade = null,
        ?Closure $preencher = null,
        bool $naFila = false,
        int $tamanhoMaximoKb = 51200,
        ?Closure $depois = null,
    ): MountableAction {
        $conexaoDo = fn (?Model $record): ?string => self::avaliar($acao, $conexao, ['record' => $record]) ?: null;
        $unidadeDo = fn (?Model $record): ?string => self::avaliar($acao, $unidade, ['record' => $record]) ?: null;
        $processoDo = fn (?Model $record): ?SeiProcesso => ProcessoDoRegistro::de($record, $conexaoDo($record));

        return $acao
            ->label('Incluir documento no SEI')
            ->icon('heroicon-o-document-plus')
            ->hidden(fn (?Model $record) => $processoDo($record) === null)
            ->modalHeading(fn (?Model $record) => 'Incluir documento no processo '.$processoDo($record)?->protocolo_formatado)
            ->modalSubmitActionLabel('Incluir documento')
            ->fillForm(fn (?Model $record) => [
                'chave' => (string) Str::uuid(),
                'tipo' => TipoDocumento::Gerado->value,
                'nivel_acesso' => NivelAcesso::Publico->value,
                'data' => now()->toDateString(),
                ...($preencher !== null ? (array) self::avaliar($acao, $preencher, ['record' => $record]) : []),
            ])
            ->form(fn (?Model $record) => [
                Forms\Components\Hidden::make('chave'),
                Forms\Components\Radio::make('tipo')
                    ->options([TipoDocumento::Gerado->value => 'Gerado no SEI (editor)', TipoDocumento::Externo->value => 'Externo (arquivo)'])
                    ->inline()
                    ->live()
                    ->required(),
                SerieSeiSelect::make('id_serie')
                    ->conexao($conexaoDo($record))
                    ->unidade($unidadeDo($record) ?? $processoDo($record)?->id_unidade_geradora)
                    ->required(),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('numero')
                        ->label('Número')
                        ->maxLength(50)
                        ->helperText('Só para tipos com número informado.'),
                    Forms\Components\TextInput::make('descricao')
                        ->label('Descrição')
                        ->maxLength(250),
                ]),
                Forms\Components\RichEditor::make('conteudo')
                    ->label('Conteúdo')
                    ->visible(fn (Get $get) => $get('tipo') === TipoDocumento::Gerado->value)
                    ->helperText('Corpo do documento. Pode ser editado depois no SEI, até a assinatura.'),
                Forms\Components\FileUpload::make('arquivo')
                    ->storeFiles(false)
                    ->maxSize($tamanhoMaximoKb)
                    ->visible(fn (Get $get) => $get('tipo') === TipoDocumento::Externo->value)
                    ->required(fn (Get $get) => $get('tipo') === TipoDocumento::Externo->value),
                Forms\Components\Grid::make(2)
                    ->visible(fn (Get $get) => $get('tipo') === TipoDocumento::Externo->value)
                    ->schema([
                        Forms\Components\DatePicker::make('data')
                            ->label('Data do documento')
                            ->required(fn (Get $get) => $get('tipo') === TipoDocumento::Externo->value),
                        Forms\Components\TextInput::make('remetente')
                            ->maxLength(250)
                            ->required(fn (Get $get) => $get('tipo') === TipoDocumento::Externo->value),
                        TipoConferenciaSeiSelect::make('id_tipo_conferencia')
                            ->conexao($conexaoDo($record))
                            ->helperText('Para documento digitalizado.'),
                    ]),
                ...GerarProcessoSeiAction::camposDeAcesso($conexaoDo($record)),
            ])
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $conexaoDo, $unidadeDo, $processoDo, $naFila, $depois) {
                $processo = $processoDo($record);
                $conexaoEscolhida = $conexaoDo($record) ?? $processo?->conexao;
                $chave = static::chave($record, (string) $data['chave']);

                try {
                    $integracao = app(SeiManager::class)->integracao($conexaoEscolhida);
                    $integracao = ($u = $unidadeDo($record)) !== null ? $integracao->naUnidade($u) : $integracao;
                    $documento = static::documento($data, $integracao->client());

                    if ($naFila) {
                        IncluirDocumentoSeiJob::dispatch($processo, $documento, $chave, $conexaoEscolhida);
                        Notification::make()->success()->title('Inclusão do documento enviada para a fila')->send();

                        return;
                    }

                    $registro = $integracao->incluirDocumento($processo, $documento, $chave);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Documento não incluído');
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title("Documento {$registro->documento_formatado} incluído no SEI")
                    ->actions(array_filter([
                        $registro->link_acesso ? NotificationAction::make('abrir')->label('Abrir no SEI')->url($registro->link_acesso, shouldOpenInNewTab: true) : null,
                    ]))
                    ->send();

                if ($depois !== null) {
                    $acao->evaluate($depois, ['documento' => $registro, 'record' => $record]);
                }
            });
    }

    /**
     * Documento do conector a partir do formulário. Arquivo maior que uma parte do upload sobe antes, em partes
     * (enviarArquivo), e o documento leva só o IdArquivo.
     *
     * @param  array<string, mixed>  $data
     */
    public static function documento(array $data, SeiClient $cliente): Documento
    {
        $nivel = NivelAcesso::from((int) ($data['nivel_acesso'] ?? 0));
        $hipotese = $nivel === NivelAcesso::Publico ? null : ($data['id_hipotese_legal'] ?? null ?: null);
        $comuns = [
            'numero' => $data['numero'] ?? null ?: null,
            'descricao' => $data['descricao'] ?? null ?: null,
            'nivelAcesso' => $nivel,
            'idHipoteseLegal' => $hipotese,
        ];

        if (($data['tipo'] ?? null) !== TipoDocumento::Externo->value) {
            return Documento::gerado((string) $data['id_serie'], ...$comuns, conteudo: $data['conteudo'] ?? null ?: null);
        }

        $arquivo = $data['arquivo'] ?? null;
        $arquivo = is_array($arquivo) ? reset($arquivo) : $arquivo;
        if (! $arquivo instanceof UploadedFile) {
            throw new \InvalidArgumentException('Escolha o arquivo do documento externo.');
        }

        $nome = $arquivo->getClientOriginalName();
        $grande = $arquivo->getSize() > $cliente->config()->uploadChunkSize;

        return Documento::externo(
            (string) $data['id_serie'],
            new DateTimeImmutable((string) $data['data']),
            $nome,
            ContatoRef::nome((string) $data['remetente']),
            ...$comuns,
            conteudo: $grande ? null : (string) file_get_contents($arquivo->getRealPath()),
            idArquivo: $grande ? $cliente->documentos()->enviarArquivo((string) $arquivo->getRealPath(), $nome) : null,
            idTipoConferencia: $data['id_tipo_conferencia'] ?? null ?: null,
        );
    }

    private static function chave(?Model $record, string $uuid): string
    {
        $origem = $record instanceof SeiProcesso
            ? 'processo:'.$record->getKey()
            : ($record?->getMorphClass() ?? 'registro').':'.$record?->getKey();

        return "{$origem}:incluirDocumento:{$uuid}";
    }
}
