<?php

namespace MonteiroTI\SeiFilament\Resources\SeiLogs;

use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use MonteiroTI\SeiFilament\Concerns\SomenteLeitura;
use MonteiroTI\SeiFilament\Models\SeiLog;
use MonteiroTI\SeiFilament\Resources\SeiLogs\Pages\ListSeiLogs;
use MonteiroTI\SeiFilament\Resources\SeiLogs\Pages\ViewSeiLog;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

/**
 * Log das chamadas ao SEI/SIP (sei_logs), gravado pelos eventos do conector.
 */
class SeiLogResource extends Resource
{
    use SomenteLeitura;

    public const PERMISSAO = 'ver_log:sei';

    protected static ?string $model = SeiLog::class;

    protected static ?string $slug = 'sei-logs';

    protected static ?string $modelLabel = 'chamada ao SEI';

    protected static ?string $pluralModelLabel = 'log das chamadas ao SEI';

    protected static ?string $navigationLabel = 'Log das chamadas';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->hasPlugin(SeiFilamentPlugin::ID)
            ? SeiFilamentPlugin::get()->getNavigationGroup()
            : null;
    }

    public static function canViewAny(): bool
    {
        return SeiFilamentPlugin::configurado()?->podeVerLogs(Filament::auth()->user()) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operacao')
                    ->label('Operação')
                    ->searchable(),
                Tables\Columns\TextColumn::make('conexao')
                    ->label('Conexão')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('duracao_ms')
                    ->label('Tempo')
                    ->formatStateUsing(fn (int $state) => static::tempo($state))
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\IconColumn::make('sucesso')
                    ->boolean(),
                Tables\Columns\TextColumn::make('erro_mensagem')
                    ->label('Erro')
                    ->limit(60)
                    ->tooltip(fn (SeiLog $record) => $record->erro_mensagem)
                    ->color('danger')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('sucesso')
                    ->label('Resultado')
                    ->trueLabel('Com sucesso')
                    ->falseLabel('Com erro'),
                Tables\Filters\SelectFilter::make('operacao')
                    ->label('Operação')
                    ->searchable()
                    ->options(fn () => SeiLog::query()->distinct()->orderBy('operacao')->pluck('operacao', 'operacao')->all()),
                Tables\Filters\SelectFilter::make('conexao')
                    ->label('Conexão')
                    ->options(fn () => SeiLog::query()->distinct()->orderBy('conexao')->pluck('conexao', 'conexao')->all()),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Chamada')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('operacao')->label('Operação'),
                    Infolists\Components\TextEntry::make('conexao')->label('Conexão'),
                    Infolists\Components\TextEntry::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
                    Infolists\Components\IconEntry::make('sucesso')->boolean(),
                    Infolists\Components\TextEntry::make('duracao_ms')->label('Tempo')->formatStateUsing(fn (int $state) => static::tempo($state)),
                    Infolists\Components\TextEntry::make('user_id')->label('Usuário')->placeholder('— (fila, agendador ou console)'),
                ]),
            Infolists\Components\Section::make('Erro')
                ->visible(fn (SeiLog $record) => ! $record->sucesso)
                ->schema([
                    Infolists\Components\TextEntry::make('erro_classe')->label('Exceção')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null),
                    Infolists\Components\TextEntry::make('fault_code')->label('Fault code')->placeholder('—'),
                    Infolists\Components\TextEntry::make('erro_mensagem')->label('Mensagem')->columnSpanFull(),
                ])
                ->columns(2),
            Infolists\Components\Section::make('Argumentos')
                ->description('Como o conector registrou: chave de acesso e dados pessoais mascarados, Base64 resumido.')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('argumentos')
                        ->hiddenLabel()
                        ->keyLabel('Parâmetro')
                        ->valueLabel('Valor')
                        ->state(fn (SeiLog $record) => static::achatar($record->argumentos ?? [])),
                ]),
        ]);
    }

    /**
     * Argumentos aninhados em linhas "Documento.IdSerie => 999" para o KeyValueEntry.
     *
     * @param  array<array-key, mixed>  $argumentos
     * @return array<string, string>
     */
    public static function achatar(array $argumentos, string $prefixo = ''): array
    {
        $linhas = [];
        foreach ($argumentos as $chave => $valor) {
            $nome = $prefixo === '' ? (string) $chave : "{$prefixo}.{$chave}";

            if (is_array($valor) && $valor !== []) {
                $linhas += static::achatar($valor, $nome);
            } else {
                $linhas[$nome] = match (true) {
                    $valor === null, $valor === [] => '—',
                    is_bool($valor) => $valor ? 'true' : 'false',
                    is_scalar($valor) => Str::limit((string) $valor, 300),
                    default => Str::limit((string) json_encode($valor), 300),
                };
            }
        }

        return $linhas;
    }

    public static function tempo(int $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 1, ',', '.').' s' : "{$ms} ms";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeiLogs::route('/'),
            'view' => ViewSeiLog::route('/{record}'),
        ];
    }
}
