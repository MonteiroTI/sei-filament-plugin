<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Resources\Pages\ListRecords;
use MonteiroTI\SeiFilament\Actions\RegistrarOuvidoriaSeiAction;

class ListProcessos extends ListRecords
{
    protected static string $resource = ProcessoResource::class;

    protected function getHeaderActions(): array
    {
        return [RegistrarOuvidoriaSeiAction::make(orgao: '0')];
    }
}
