<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;

class ProcessoResource extends Resource
{
    protected static ?string $model = Processo::class;

    protected static ?string $slug = 'processos';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('protocolo_sei')])
            ->actions([ConsultarProcessoSeiAction::tabela()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessos::route('/'),
            'view' => ViewProcesso::route('/{record}'),
        ];
    }
}
