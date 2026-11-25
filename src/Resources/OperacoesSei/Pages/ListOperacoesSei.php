<?php

namespace MonteiroTI\SeiFilament\Resources\OperacoesSei\Pages;

use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTI\SeiFilament\Concerns\ResourceEmUso;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\OperacaoSeiResource;

class ListOperacoesSei extends ListRecords
{
    use ResourceEmUso;

    protected static string $resource = OperacaoSeiResource::class;

    public function getTabs(): array
    {
        return [
            // Falhou e descartada o conector repete sozinho; só a incerta espera alguém conferir no SEI.
            'incertas' => Tab::make('Incertas')
                ->badge(fn () => static::getResource()::getNavigationBadge())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StatusOperacao::Incerta)),
            'todas' => Tab::make('Todas'),
        ];
    }
}
