<?php

namespace MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages;

use Filament\Resources\Pages\ListRecords;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;

class ListProcessosSei extends ListRecords
{
    use ResourceEmUso;

    protected static string $resource = ProcessoSeiResource::class;
}
