<?php

namespace MonteiroTI\SeiFilament\Resources\SeiLogs\Pages;

use Filament\Resources\Pages\ViewRecord;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;

class ViewSeiLog extends ViewRecord
{
    use ResourceEmUso;

    protected static string $resource = SeiLogResource::class;
}
