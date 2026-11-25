<?php

namespace MonteiroTI\SeiFilament;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Avisos\AvisosDoSei;
use MonteiroTI\SeiFilament\Logs\GravarChamadasSei;
use MonteiroTI\SeiFilament\Support\ConexoesNoConfig;

class SeiFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // As conexões do banco (e o prefixo do cache dos catálogos) entram no config/sei.php na primeira vez que o
        // SEI é usado no processo: request sem SEI não consulta o banco.
        $this->callAfterResolving(SeiManager::class, fn () => ConexoesNoConfig::aplicar());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sei-filament');

        // Subscriber global: as chamadas também saem da fila e do agendador, fora de qualquer painel.
        Event::subscribe(GravarChamadasSei::class);
        Event::subscribe(AvisosDoSei::class);

        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_sei_conexoes_table.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_sei_conexoes_table.php'),
                __DIR__.'/../database/migrations/create_sei_logs_table.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His', time() + 1).'_create_sei_logs_table.php'),
            ], 'sei-filament-migrations');
        }
    }
}
