<?php

namespace MonteiroTI\SeiFilament\Tests;

use Filament\Facades\Filament;
use InvalidArgumentException;
use MonteiroTI\SeiFilament\Pages\CatalogosSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Widgets\SituacaoSeiWidget;

class PluginTest extends TestCase
{
    public function test_registra_as_telas_no_painel(): void
    {
        $painel = Filament::getPanel('admin');

        $this->assertContains(ConexaoSeiResource::class, $painel->getResources());
        $this->assertContains(SeiLogResource::class, $painel->getResources());
        $this->assertContains(CatalogosSei::class, $painel->getPages());
        $this->assertContains(SituacaoSeiWidget::class, $painel->getWidgets());
        $this->assertSame('Integrações', SeiFilamentPlugin::get()->getNavigationGroup());
    }

    public function test_desliga_telas(): void
    {
        $plugin = SeiFilamentPlugin::make()->conexoes(false)->logs(false)->catalogos(false)->widget(false);
        $painel = Filament::getPanel('admin');
        $plugin->register($painel);

        $this->assertFalse($plugin->temLogs());
        $this->assertFalse($plugin->temConexoes());
    }

    public function test_subclasse_precisa_estender_a_tela_do_plugin(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SeiFilamentPlugin::make()->logs(resource: ConexaoSeiResource::class);
    }

    public function test_autorizacao_pelo_gate_ou_pela_regra_do_app(): void
    {
        $plugin = SeiFilamentPlugin::get();
        $ana = $this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]);
        $bia = $this->usuario('Bia');

        $this->assertTrue($plugin->podeConfigurar($ana));
        $this->assertFalse($plugin->podeConfigurar($bia));
        $this->assertFalse($plugin->podeConfigurar(null));

        $plugin->autorizarConfiguracao(fn ($user) => $user->name === 'Bia');
        $this->assertTrue($plugin->podeConfigurar($bia));
        $this->assertFalse($plugin->podeConfigurar($ana));
    }
}
