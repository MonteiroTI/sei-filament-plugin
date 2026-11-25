<?php

namespace MonteiroTI\SeiFilament\Actions;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Enum\TipoBloco;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Actions\Concerns\FabricasDeAcao;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;
use MonteiroTI\SeiFilament\Pages\BlocosSei;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\DocumentoDoRegistro;
use MonteiroTI\SeiFilament\Support\Erros;
use Throwable;

/**
 * Põe documentos num bloco de assinatura: um bloco novo (que pode já ser disponibilizado para outras unidades) ou um
 * existente da unidade. Também em massa, numa tabela de documentos (emMassa()).
 *
 * Sem $documento: o documento_formatado do SeiDocumento ou o atributo documento_sei do registro.
 */
final class IncluirEmBlocoSeiAction
{
    use FabricasDeAcao;

    public const NOME = 'incluirEmBlocoSei';

    /**
     * @param  (Closure(Model): ?string)|string|null  $conexao
     * @param  (Closure(Model): ?string)|string|null  $unidade  unidade dona do bloco; padrão: a da conexão
     * @param  (Closure(Model): ?string)|null  $documento  número do documento no SEI
     */
    protected static function configurar(
        MountableAction $acao,
        Closure|string|null $conexao = null,
        Closure|string|null $unidade = null,
        ?Closure $documento = null,
    ): MountableAction {
        $documentoDo = fn (?Model $record): ?string => DocumentoDoRegistro::protocolo($acao, $documento, $record);

        return self::montar($acao, $conexao, $unidade)
            ->hidden(fn (?Model $record) => $documentoDo($record) === null)
            ->action(function (?Model $record, array $data, MountableAction $action) use ($acao, $conexao, $unidade, $documentoDo) {
                static::incluir($action, [(string) $documentoDo($record)], $data,
                    static::avaliar($acao, $conexao, ['record' => $record]) ?: ($record instanceof SeiDocumento ? $record->conexao : null),
                    static::avaliar($acao, $unidade, ['record' => $record]) ?: null);
            });
    }

    /**
     * Para a tabela de documentos: os selecionados num bloco só.
     *
     * @param  (Closure(Model): ?string)|null  $documento
     */
    public static function emMassa(?string $nome = null, ?string $conexao = null, ?string $unidade = null, ?Closure $documento = null): BulkAction
    {
        $acao = BulkAction::make($nome ?? self::NOME);

        return self::montar($acao, $conexao, $unidade)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action) use ($acao, $conexao, $unidade, $documento) {
                $documentos = $records->map(fn (Model $r) => DocumentoDoRegistro::protocolo($acao, $documento, $r))->filter()->values()->all();
                $primeiro = $records->first();

                static::incluir($action, $documentos, $data,
                    $conexao ?? ($primeiro instanceof SeiDocumento ? $primeiro->conexao : null),
                    $unidade);
            });
    }

    /**
     * @template T of MountableAction
     *
     * @param  T  $acao
     * @return T
     */
    private static function montar(MountableAction $acao, Closure|string|null $conexao, Closure|string|null $unidade): MountableAction
    {
        return $acao
            ->label('Incluir em bloco de assinatura')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading('Incluir em bloco de assinatura')
            ->modalSubmitActionLabel('Incluir')
            ->fillForm(['bloco' => 'novo', 'disponibilizar' => true])
            ->form(fn () => [
                Forms\Components\Radio::make('bloco')
                    ->hiddenLabel()
                    ->options(['novo' => 'Bloco novo', 'existente' => 'Bloco que já existe'])
                    ->inline()
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('id_bloco')
                    ->label('Número do bloco')
                    ->visible(fn (Get $get) => $get('bloco') === 'existente')
                    ->required(fn (Get $get) => $get('bloco') === 'existente'),
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição do bloco')
                    ->maxLength(250)
                    ->visible(fn (Get $get) => $get('bloco') === 'novo')
                    ->required(fn (Get $get) => $get('bloco') === 'novo'),
                UnidadeSeiSelect::make('unidades')
                    ->label('Disponibilizar para as unidades')
                    ->conexao(is_string($conexao) ? $conexao : null)
                    ->multiple()
                    ->visible(fn (Get $get) => $get('bloco') === 'novo'),
                Forms\Components\Toggle::make('disponibilizar')
                    ->label('Disponibilizar já')
                    ->visible(fn (Get $get) => $get('bloco') === 'novo'),
                Forms\Components\Textarea::make('anotacao')
                    ->label('Anotação')
                    ->rows(2)
                    ->visible(fn (Get $get) => $get('bloco') === 'existente'),
            ]);
    }

    /**
     * @param  list<string>  $documentos
     * @param  array<string, mixed>  $data
     */
    private static function incluir(MountableAction $action, array $documentos, array $data, ?string $conexao, ?string $unidade): void
    {
        try {
            $cliente = app(SeiManager::class)->connection($conexao);
            $blocos = ($unidade !== null ? $cliente->naUnidade($unidade) : $cliente)->blocos();

            if ($data['bloco'] === 'novo') {
                $idBloco = $blocos->gerar(
                    TipoBloco::Assinatura,
                    (string) $data['descricao'],
                    array_values(array_map('strval', (array) ($data['unidades'] ?? []))),
                    $documentos,
                    ($data['disponibilizar'] ?? false) && ! empty($data['unidades']) ? true : null,
                );
            } else {
                $idBloco = trim((string) $data['id_bloco']);
                foreach ($documentos as $documento) {
                    $blocos->incluirDocumento($idBloco, $documento, $data['anotacao'] ?? null ?: null);
                }
            }
        } catch (Throwable $e) {
            if (! Erros::trata($e)) {
                throw $e;
            }
            Erros::notificar($e, 'Documento não incluído no bloco');
            $action->halt();
        }

        $pagina = SeiFilamentPlugin::configurado()?->temBlocos() && SeiFilamentPlugin::classeEmUso(BlocosSei::class)::canAccess()
            ? SeiFilamentPlugin::classeEmUso(BlocosSei::class)::getUrl(['bloco' => $idBloco, 'conexao' => $conexao, 'unidade' => $unidade])
            : null;

        Notification::make()
            ->success()
            ->title(count($documentos) === 1 ? "Documento incluído no bloco {$idBloco}" : count($documentos)." documentos incluídos no bloco {$idBloco}")
            ->actions(array_filter([$pagina ? NotificationAction::make('ver')->label('Ver o bloco')->url($pagina) : null]))
            ->send();
    }
}
