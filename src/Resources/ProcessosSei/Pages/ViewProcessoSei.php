<?php

namespace MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Actions\ConcluirProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\EnviarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\IncluirDocumentoSeiAction;
use MonteiroTI\SeiFilament\Actions\ReabrirProcessoSeiAction;
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
            ActionGroup::make([
                IncluirDocumentoSeiAction::make()->label('Incluir documento')->after(fn () => $this->dispatch('$refresh')),
                EnviarProcessoSeiAction::make(),
                ConcluirProcessoSeiAction::make(),
                ReabrirProcessoSeiAction::make(),
            ])
                ->label('Operar no SEI')
                ->button()
                ->color('primary')
                ->icon('heroicon-m-chevron-down')
                ->visible(fn () => ProcessoSeiResource::podeOperar()),
            Action::make('abrirNoSei')
                ->label('Abrir no SEI')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (SeiProcesso $record) => $record->link_acesso, shouldOpenInNewTab: true)
                ->visible(fn (SeiProcesso $record) => filled($record->link_acesso)),
        ];
    }
}
