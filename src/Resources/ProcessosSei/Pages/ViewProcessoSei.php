<?php

namespace MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;

class ViewProcessoSei extends ViewRecord
{
    use ResourceEmUso;

    protected static string $resource = ProcessoSeiResource::class;

    public function getTitle(): string
    {
        return 'Processo '.$this->getRecord()->getAttribute('protocolo_formatado');
    }

    protected function getHeaderActions(): array
    {
        return [
            ConsultarProcessoSeiAction::make(
                protocolo: fn (SeiProcesso $record) => $record->protocolo_formatado,
                conexao: fn (SeiProcesso $record) => $record->conexao,
            ),
            ProcessoSeiResource::acaoSincronizar(Action::make('sincronizar')),
            Action::make('abrirNoSei')
                ->label('Abrir no SEI')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (SeiProcesso $record) => $record->link_acesso, shouldOpenInNewTab: true)
                ->visible(fn (SeiProcesso $record) => filled($record->link_acesso)),
        ];
    }
}
