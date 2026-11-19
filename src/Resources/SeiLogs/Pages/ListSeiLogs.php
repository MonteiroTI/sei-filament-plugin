<?php

namespace MonteiroTI\SeiFilament\Resources\SeiLogs\Pages;

use Filament\Resources\Pages\ListRecords;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;

class ListSeiLogs extends ListRecords
{
    use ResourceEmUso;

    protected static string $resource = SeiLogResource::class;
}
