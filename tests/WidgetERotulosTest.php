<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Enum\EstadoBloco;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTI\SeiFilament\Models\ConexaoSei;
use MonteiroTI\SeiFilament\Models\SeiLog;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;
use MonteiroTI\SeiFilament\Support\Rotulos;
use MonteiroTI\SeiFilament\Widgets\SituacaoSeiWidget;

class WidgetERotulosTest extends TestCase
{
    public function test_widget_mostra_o_ultimo_teste_e_as_falhas(): void
    {
        $this->actingAs($this->usuario('Ana', [ConexaoSeiResource::PERMISSAO]));
        ConexaoSei::query()->create([
            'nome' => 'default', 'wsdl' => 'https://x.dev', 'sigla_sistema' => 'S', 'identificacao_servico' => 'k',
            'testada_em' => now()->subMinutes(2), 'teste_ok' => true,
        ]);
        SeiLog::query()->create(['conexao' => 'sei:default', 'operacao' => 'a', 'sucesso' => true, 'duracao_ms' => 300, 'created_at' => now()]);
        SeiLog::query()->create(['conexao' => 'sei:default', 'operacao' => 'b', 'sucesso' => false, 'duracao_ms' => 10000, 'created_at' => now()]);

        Livewire::test(SituacaoSeiWidget::class)
            ->assertSee('SEI · default')
            ->assertSee('No ar')
            ->assertSee('de 2 chamada(s)')
            ->assertSee('300 ms');
    }

    public function test_widget_so_para_quem_configura_ou_ve_o_log(): void
    {
        $this->actingAs($this->usuario('Bia'));

        $this->assertFalse(SituacaoSeiWidget::canView());
    }

    public function test_rotulos_dos_enums(): void
    {
        $this->assertSame('Público', Rotulos::de(NivelAcesso::Publico));
        $this->assertSame('danger', Rotulos::cor(NivelAcesso::Sigiloso));
        $this->assertSame('Concluído', Rotulos::de(EstadoBloco::Concluido));
        $this->assertSame([0 => 'Público', 1 => 'Restrito', 2 => 'Sigiloso'], Rotulos::opcoes(NivelAcesso::class));
        $this->assertNull(Rotulos::de(null));
    }
}
