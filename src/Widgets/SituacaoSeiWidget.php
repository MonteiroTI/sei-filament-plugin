<?php

namespace MonteiroTI\SeiFilament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\QueryException;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Models\SeiLog;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

/**
 * Situação do SEI no dashboard: último teste da conexão padrão e, com o log ligado, falhas e tempo médio em 24 h.
 * Só lê o banco: não chama o SEI a cada carga do dashboard.
 */
class SituacaoSeiWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        $plugin = SeiFilamentPlugin::configurado();
        $user = Filament::auth()->user();

        return $plugin !== null && ($plugin->podeConfigurar($user) || $plugin->podeVerLogs($user));
    }

    protected function getStats(): array
    {
        $plugin = SeiFilamentPlugin::configurado();
        $stats = [$this->conexaoPadrao()];

        if ($plugin?->temLogs()) {
            try {
                $stats = [...$stats, ...$this->chamadas()];
            } catch (QueryException) {
                // Sem a tabela do log ainda.
            }
        }

        return $stats;
    }

    private function conexaoPadrao(): Stat
    {
        $nome = app(SeiManager::class)->getDefaultConnection();

        try {
            $conexao = ConexaoSei::query()->where('nome', $nome)->where('ativo', true)->first();
        } catch (QueryException) {
            $conexao = null;
        }

        if ($conexao?->testada_em === null) {
            return Stat::make("SEI · {$nome}", 'Não testada')
                ->description($conexao === null ? 'Conexão do config/sei.php' : 'Use "Testar" em Conexões')
                ->color('gray')
                ->icon('heroicon-o-signal');
        }

        return Stat::make("SEI · {$nome}", $conexao->teste_ok ? 'No ar' : 'Com falha')
            ->description('Testada '.$conexao->testada_em->diffForHumans())
            ->descriptionIcon($conexao->teste_ok ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
            ->color($conexao->teste_ok ? 'success' : 'danger')
            ->icon('heroicon-o-signal');
    }

    /**
     * @return list<Stat>
     */
    private function chamadas(): array
    {
        $desde = now()->subDay();
        $total = SeiLog::query()->where('created_at', '>=', $desde)->count();
        $falhas = SeiLog::query()->where('created_at', '>=', $desde)->where('sucesso', false)->count();
        $media = (int) round((float) SeiLog::query()->where('created_at', '>=', $desde)->where('sucesso', true)->avg('duracao_ms'));

        return [
            Stat::make('Falhas nas últimas 24 h', number_format($falhas, 0, ',', '.'))
                ->description('de '.number_format($total, 0, ',', '.').' chamada(s)')
                ->color($falhas > 0 ? 'danger' : 'success')
                ->url(SeiLogResource::canViewAny() ? SeiFilamentPlugin::classeEmUso(SeiLogResource::class)::getUrl() : null),
            Stat::make('Tempo médio', $total > 0 ? SeiLogResource::tempo($media) : '—')
                ->description('chamadas com sucesso, 24 h'),
        ];
    }
}
