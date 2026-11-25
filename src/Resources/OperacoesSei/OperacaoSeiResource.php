<?php

namespace MonteiroTI\SeiFilament\Resources\OperacoesSei;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Concerns\SomenteLeitura;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages\ListOperacoesSei;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages\ViewOperacaoSei;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Support\Erros;
use MonteiroTI\SeiFilament\Support\Rotulos;
use MonteiroTI\SeiFilament\Support\Vinculo;
use Throwable;

/**
 * Operações com idempotência do conector (sei_operacoes). As incertas (timeout depois do envio) travam novas
 * tentativas até alguém conferir no SEI e resolver: o mesmo que o sei:operacoes:resolver.
 */
class OperacaoSeiResource extends Resource
{
    use SomenteLeitura;

    public const PERMISSAO_RESOLVER = 'resolver_operacoes:sei';

    protected static ?string $slug = 'sei-operacoes';

    protected static ?string $modelLabel = 'operação no SEI';

    protected static ?string $pluralModelLabel = 'operações no SEI';

    protected static ?string $navigationLabel = 'Operações';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'chave';

    /** O model configurado em sei.persistencia.models.operacao. */
    public static function getModel(): string
    {
        return SeiModel::classe('operacao', SeiOperacao::class);
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

    /** Incertas no menu: cada uma trava a operação dela até ser resolvida. */
    public static function getNavigationBadge(): ?string
    {
        try {
            $incertas = static::getModel()::query()->where('status', StatusOperacao::Incerta)->count();
        } catch (QueryException) {
            return null;
        }

        return $incertas > 0 ? (string) $incertas : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Operações incertas: confira no SEI e resolva';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operacao')
                    ->label('Operação'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (StatusOperacao $state) => Rotulos::de($state))
                    ->color(fn (StatusOperacao $state) => Rotulos::cor($state)),
                Tables\Columns\TextColumn::make('chave')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (SeiOperacao $record) => $record->chave)
                    ->copyable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('vinculavel_type')
                    ->label('Registro do sistema')
                    ->state(fn (SeiOperacao $record) => Vinculo::texto($record->vinculavel_type, $record->vinculavel_id))
                    ->url(fn (SeiOperacao $record) => Vinculo::url($record->vinculavel))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tentativas')
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('erro')
                    ->limit(50)
                    ->tooltip(fn (SeiOperacao $record) => $record->erro)
                    ->color('danger')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('conexao')
                    ->label('Conexão')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Rotulos::opcoes(StatusOperacao::class)),
                Tables\Filters\SelectFilter::make('operacao')
                    ->label('Operação')
                    ->options(fn () => static::getModel()::query()->distinct()->orderBy('operacao')->pluck('operacao', 'operacao')->all()),
            ])
            ->actions([
                static::acaoResolver(Tables\Actions\Action::make('resolver')),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Operação')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('chave')->copyable()->columnSpanFull(),
                    Infolists\Components\TextEntry::make('operacao')->label('Operação'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (StatusOperacao $state) => Rotulos::de($state))
                        ->color(fn (StatusOperacao $state) => Rotulos::cor($state)),
                    Infolists\Components\TextEntry::make('conexao')->label('Conexão'),
                    Infolists\Components\TextEntry::make('vinculavel_type')
                        ->label('Registro do sistema')
                        ->state(fn (SeiOperacao $record) => Vinculo::texto($record->vinculavel_type, $record->vinculavel_id))
                        ->url(fn (SeiOperacao $record) => Vinculo::url($record->vinculavel))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('tentativas'),
                    Infolists\Components\TextEntry::make('iniciada_em')->label('Iniciada em')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    Infolists\Components\TextEntry::make('concluida_em')->label('Concluída em')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    Infolists\Components\TextEntry::make('erro')->color('danger')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Resolver depois de conferir no SEI: informar o processo ou documento que o SEI criou (vira vínculo), marcar
     * como executada sem vínculo, ou descartar (não foi executada: libera nova tentativa).
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  T  $acao
     * @return T
     */
    public static function acaoResolver(Tables\Actions\Action|Action $acao): Tables\Actions\Action|Action
    {
        return $acao
            ->label('Resolver')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (SeiOperacao $record) => $record->status === StatusOperacao::Incerta
                && (SeiFilamentPlugin::configurado()?->podeResolverOperacoes(Filament::auth()->user()) ?? false))
            ->modalHeading('Resolver operação')
            ->modalDescription(fn (SeiOperacao $record) => "Confira no SEI se \"{$record->operacao}\" foi executada antes de escolher. Chave: {$record->chave}")
            ->modalSubmitActionLabel('Resolver')
            ->form(fn (SeiOperacao $record) => [
                Forms\Components\Radio::make('como')
                    ->label('No SEI, a operação...')
                    ->required()
                    ->live()
                    ->options(array_filter([
                        'processo' => $record->operacao === 'gerarProcedimento' ? 'Foi executada: informar o processo criado' : null,
                        'documento' => $record->operacao === 'incluirDocumento' ? 'Foi executada: informar o documento criado' : null,
                        'concluida' => 'Foi executada (sem vínculo a criar)',
                        'descartar' => 'Não foi executada: liberar nova tentativa',
                    ])),
                Forms\Components\TextInput::make('protocolo')
                    ->label('Número do processo no SEI')
                    ->placeholder('53000.012345/2026-11')
                    ->visible(fn (Get $get) => $get('como') === 'processo')
                    ->required(fn (Get $get) => $get('como') === 'processo'),
                Forms\Components\TextInput::make('documento')
                    ->label('Número do documento no SEI')
                    ->placeholder('0012345')
                    ->visible(fn (Get $get) => $get('como') === 'documento')
                    ->required(fn (Get $get) => $get('como') === 'documento'),
            ])
            ->action(function (SeiOperacao $record, array $data, $action) {
                try {
                    static::resolver($record, $data);
                } catch (Throwable $e) {
                    if (! Erros::trata($e)) {
                        throw $e;
                    }
                    Erros::notificar($e, 'Não foi possível resolver');
                    $action->halt();
                }

                $status = Rotulos::de($record->refresh()->status);
                Notification::make()->success()->title("Operação marcada como {$status}")->send();
            });
    }

    /**
     * @param  array{como: string, protocolo?: string|null, documento?: string|null}  $dados
     */
    public static function resolver(SeiOperacao $operacao, array $dados): void
    {
        $integracao = fn () => app(SeiManager::class)->integracao($operacao->conexao);

        match ($dados['como']) {
            'processo' => $integracao()->resolverComProcesso($operacao, trim((string) $dados['protocolo'])),
            'documento' => $integracao()->resolverComDocumento($operacao, trim((string) $dados['documento'])),
            'concluida' => $operacao->forceFill(['status' => StatusOperacao::Concluida, 'concluida_em' => now(), 'erro' => null])->save(),
            'descartar' => $operacao->forceFill(['status' => StatusOperacao::Descartada])->save(),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperacoesSei::route('/'),
            'view' => ViewOperacaoSei::route('/{record}'),
        ];
    }
}
