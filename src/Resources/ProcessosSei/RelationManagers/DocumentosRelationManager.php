<?php

namespace MonteiroTI\SeiFilament\Resources\ProcessosSei\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Enum\TipoDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTI\SeiFilament\Support\Rotulos;

/**
 * Documentos do processo que o sistema incluiu ou acompanha (sei_documentos), com assinatura e publicação.
 */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('documento_formatado')
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('documento_formatado')
                    ->label('Documento')
                    ->copyable()
                    ->url(fn (SeiDocumento $record) => $record->link_acesso, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => Rotulos::de(TipoDocumento::tryFrom((string) $state)) ?? $state),
                Tables\Columns\TextColumn::make('id_serie')
                    ->label('Série (ID)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assinado_em')
                    ->label('Assinado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('não')
                    ->tooltip(fn (SeiDocumento $record) => collect($record->assinaturas ?? [])
                        ->map(fn ($a) => trim(($a['nome'] ?? '').' · '.($a['cargo'] ?? ''), ' ·'))
                        ->filter()
                        ->join("\n") ?: null),
                Tables\Columns\TextColumn::make('publicacao_estado')
                    ->label('Publicação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Rotulos::de(EstadoPublicacao::tryFrom((string) $state)))
                    ->color(fn (?string $state) => Rotulos::cor(EstadoPublicacao::tryFrom((string) $state)))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sincronizado_em')
                    ->label('Sincronizado')
                    ->since()
                    ->placeholder('nunca')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('Nenhum documento vinculado')
            ->emptyStateDescription('Aparecem aqui os documentos incluídos pelo sistema (Sei::integracao()->incluirDocumento).');
    }
}
