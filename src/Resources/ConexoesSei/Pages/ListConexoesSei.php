<?php

namespace MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;

class ListConexoesSei extends ListRecords
{
    use ResourceEmUso;

    protected static string $resource = ConexaoSeiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nova conexão')];
    }
}
