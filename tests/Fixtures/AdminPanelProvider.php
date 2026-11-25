<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->middleware([EncryptCookies::class, StartSession::class, ShareErrorsFromSession::class])
            ->pages([Dashboard::class])
            ->resources([ProcessoResource::class])
            ->plugin(SeiFilamentPlugin::make()->navigationGroup('Integrações')->vinculos());
    }
}
