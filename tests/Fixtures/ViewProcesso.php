<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTI\SeiFilament\Actions\ConcluirProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\EnviarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\GerarProcessoSeiAction;
use MonteiroTI\SeiFilament\Actions\IncluirDocumentoSeiAction;
use MonteiroTI\SeiFilament\Actions\ReabrirProcessoSeiAction;

class ViewProcesso extends ViewRecord
{
    protected static string $resource = ProcessoResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('protocolo_sei')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ConsultarProcessoSeiAction::make(tarefas: [1]),
            GerarProcessoSeiAction::make(
                preencher: fn (Processo $record) => ['id_tipo_procedimento' => '100', 'especificacao' => "Processo #{$record->getKey()}"],
                documentos: fn (array $data) => [Documento::gerado('12', '<p>Requerimento</p>')],
            ),
            IncluirDocumentoSeiAction::make(),
            EnviarProcessoSeiAction::make(),
            ConcluirProcessoSeiAction::make(),
            ReabrirProcessoSeiAction::make(),
        ];
    }
}
