<?php

namespace MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;

class EditConexaoSei extends EditRecord
{
    use ResourceEmUso;

    protected static string $resource = ConexaoSeiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConexaoSeiResource::acaoTestar(Action::make('testar'))->label('Testar conexão'),
            DeleteAction::make(),
        ];
    }
}
