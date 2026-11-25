<?php

namespace MonteiroTI\SeiFilament\Resources\ProcessosSei;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;
use MonteiroTI\SeiFilament\Concerns\SomenteLeitura;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ListProcessosSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ViewProcessoSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\RelationManagers\DocumentosRelationManager;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Rotulos;
use MonteiroTI\SeiFilament\Support\Vinculo;
use Throwable;

/**
 * Processos do SEI que o sistema gerou ou acompanha (sei_processos do conector), com os documentos e a sincronização.
 */
class ProcessoSeiResource extends Resource
{
    use SomenteLeitura;

    public const PERMISSAO = 'ver_vinculos:sei';

    protected static ?string $slug = 'sei-processos';

    protected static ?string $modelLabel = 'processo do SEI';

    protected static ?string $pluralModelLabel = 'processos do SEI';

    protected static ?string $navigationLabel = 'Processos vinculados';

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'protocolo_formatado';

    /** O model configurado em sei.persistencia.models.processo. */
    public static function getModel(): string
    {
        return SeiModel::classe('processo', SeiProcesso::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public static function canViewAny(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeVerVinculos(Filament::auth()->user()) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('protocolo_formatado')
                    ->label('Processo')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('vinculavel_type')
                    ->label('Registro do sistema')
                    ->state(fn (SeiProcesso $record) => Vinculo::texto($record->vinculavel_type, $record->vinculavel_id))
                    ->url(fn (SeiProcesso $record) => Vinculo::url($record->vinculavel))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('conexao')
                    ->label('Conexão')
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nivel_acesso')
                    ->label('Acesso')
                    ->badge()
                    ->formatStateUsing(fn (?int $state) => Rotulos::de(NivelAcesso::tryFrom((int) $state)))
                    ->color(fn (?int $state) => Rotulos::cor(NivelAcesso::tryFrom((int) $state))),
                Tables\Columns\TextColumn::make('aberto')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aberto' : 'Concluído')
                    ->color(fn (bool $state) => $state ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('unidades_abertas')
                    ->label('Aberto em')
                    ->state(fn (SeiProcesso $record) => static::unidadesAbertas($record))
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('ultimo_andamento_em')
                    ->label('Último andamento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sincronizado_em')
                    ->label('Sincronizado')
                    ->since()
                    ->placeholder('nunca')
                    ->color(fn (SeiProcesso $record) => $record->erro_sincronizacao ? 'danger' : null)
                    ->tooltip(fn (SeiProcesso $record) => $record->erro_sincronizacao)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aberto')
                    ->label('Situação')
                    ->trueLabel('Abertos')
                    ->falseLabel('Concluídos'),
                Tables\Filters\SelectFilter::make('conexao')
                    ->label('Conexão')
                    ->options(fn () => static::getModel()::query()->distinct()->orderBy('conexao')->pluck('conexao', 'conexao')->all()),
                Tables\Filters\Filter::make('erro_sincronizacao')
                    ->label('Com erro na sincronização')
                    ->query(fn (Builder $query) => $query->whereNotNull('erro_sincronizacao')),
            ])
            ->actions([
                ConsultarProcessoSeiAction::tabela(
                    protocolo: fn (SeiProcesso $record) => $record->protocolo_formatado,
                    conexao: fn (SeiProcesso $record) => $record->conexao,
                ),
                static::acaoSincronizar(Tables\Actions\Action::make('sincronizar')),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Processo')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('protocolo_formatado')
                        ->label('Processo')
                        ->copyable()
                        ->url(fn (SeiProcesso $record) => $record->link_acesso, shouldOpenInNewTab: true),
                    Infolists\Components\TextEntry::make('id_procedimento')->label('ID no SEI'),
                    Infolists\Components\TextEntry::make('conexao')->label('Conexão'),
                    Infolists\Components\TextEntry::make('vinculavel_type')
                        ->label('Registro do sistema')
                        ->state(fn (SeiProcesso $record) => Vinculo::texto($record->vinculavel_type, $record->vinculavel_id))
                        ->url(fn (SeiProcesso $record) => Vinculo::url($record->vinculavel))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('id_tipo_procedimento')->label('Tipo de processo (ID)')->placeholder('—'),
                    Infolists\Components\TextEntry::make('id_unidade_geradora')->label('Unidade geradora (ID)')->placeholder('—'),
                    Infolists\Components\TextEntry::make('nivel_acesso')
                        ->label('Nível de acesso')
                        ->badge()
                        ->formatStateUsing(fn (?int $state) => Rotulos::de(NivelAcesso::tryFrom((int) $state)))
                        ->color(fn (?int $state) => Rotulos::cor(NivelAcesso::tryFrom((int) $state))),
                    Infolists\Components\TextEntry::make('aberto')
                        ->label('Situação')
                        ->badge()
                        ->formatStateUsing(fn (bool $state) => $state ? 'Aberto' : 'Concluído')
                        ->color(fn (bool $state) => $state ? 'info' : 'gray'),
                    Infolists\Components\TextEntry::make('unidades_abertas')
                        ->label('Aberto em')
                        ->state(fn (SeiProcesso $record) => static::unidadesAbertas($record))
                        ->placeholder('—'),
                ]),
            Infolists\Components\Section::make('Sincronização')
                ->columns(3)
                ->schema([
                    Infolists\Components\IconEntry::make('sincronizar')->label('Sincroniza')->boolean(),
                    Infolists\Components\TextEntry::make('sincronizado_em')->label('Sincronizado em')->dateTime('d/m/Y H:i')->placeholder('nunca'),
                    Infolists\Components\TextEntry::make('proxima_sincronizacao_em')->label('Próxima')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('ultimo_andamento_em')->label('Último andamento')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('concluido_em')->label('Concluído em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('erro_sincronizacao')
                        ->label('Erro')
                        ->color('danger')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * "Sincronizar agora": o mesmo que o sei:sincronizar faz com o processo, na hora. Religa a sincronização que o
     * conector desligou por erro (ex.: processo que ficou sigiloso e voltou).
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  T  $acao
     * @return T
     */
    public static function acaoSincronizar(Tables\Actions\Action|Action $acao): Tables\Actions\Action|Action
    {
        return $acao
            ->label('Sincronizar')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->hidden(fn (SeiProcesso $record) => $record->sigiloso)
            ->action(function (SeiProcesso $record) {
                $record->forceFill(['sincronizar' => true, 'erro_sincronizacao' => null])->save();

                try {
                    app(SeiManager::class)->sincronizador()->processo($record);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Não foi possível sincronizar');

                    return;
                }

                $record->refresh();

                if ($record->erro_sincronizacao !== null) {
                    Notification::make()->danger()->title('O SEI recusou a consulta')->body($record->erro_sincronizacao)->persistent()->send();

                    return;
                }

                Notification::make()->success()->title("Processo {$record->protocolo_formatado} sincronizado")->send();
            });
    }

    public static function unidadesAbertas(SeiProcesso $processo): ?string
    {
        $siglas = array_filter(array_map(
            fn ($u) => is_array($u) ? ($u['sigla'] ?? $u['id'] ?? null) : null,
            $processo->unidades_abertas ?? [],
        ));

        return $siglas === [] ? null : implode(', ', $siglas);
    }

    public static function getRelations(): array
    {
        return [DocumentosRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessosSei::route('/'),
            'view' => ViewProcessoSei::route('/{record}'),
        ];
    }
}
