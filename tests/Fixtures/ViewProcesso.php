<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;

class ViewProcesso extends ViewRecord
{
    protected static string $resource = ProcessoResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('protocolo_sei')]);
    }

    protected function getHeaderActions(): array
    {
        return [ConsultarProcessoSeiAction::make(tarefas: [1])];
    }
}
