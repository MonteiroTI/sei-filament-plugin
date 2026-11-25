<?php

namespace MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\OperacaoSeiResource;

class ViewOperacaoSei extends ViewRecord
{
    use ResourceEmUso;

    protected static string $resource = OperacaoSeiResource::class;

    protected function getHeaderActions(): array
    {
        return [OperacaoSeiResource::acaoResolver(Action::make('resolver'))];
    }
}
