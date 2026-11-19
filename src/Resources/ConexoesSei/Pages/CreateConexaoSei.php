<?php

namespace MonteiroTI\SeiFilament\Resources\ConexoesSei\Pages;

use Filament\Resources\Pages\CreateRecord;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;

class CreateConexaoSei extends CreateRecord
{
    use ResourceEmUso;

    protected static string $resource = ConexaoSeiResource::class;

    protected function getRedirectUrl(): string
    {
        // Volta para a edição, onde fica o "Testar conexão".
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
