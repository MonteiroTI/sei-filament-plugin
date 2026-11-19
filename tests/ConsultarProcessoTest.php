<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;
use MonteiroTI\SeiFilament\Tests\Fixtures\ListProcessos;
use MonteiroTI\SeiFilament\Tests\Fixtures\Processo;
use MonteiroTI\SeiFilament\Tests\Fixtures\ViewProcesso;

class ConsultarProcessoTest extends TestCase
{
    private static function processo(): object
    {
        return (object) [
            'IdProcedimento' => '77',
            'ProcedimentoFormatado' => '53000.012345/2026-11',
            'DataAutuacao' => '17/11/2026',
            'NivelAcessoGlobal' => '1',
            'LinkAcesso' => 'https://sei.teste.dev/sei/controlador.php?acao=procedimento_trabalhar&id=77',
            'TipoProcedimento' => (object) ['IdTipoProcedimento' => '100', 'Nome' => 'Pessoal: Férias'],
            'UnidadesProcedimentoAberto' => [
                (object) ['Unidade' => (object) ['IdUnidade' => '2', 'Sigla' => 'SEAD-RH'], 'UsuarioAtribuicao' => (object) ['IdUsuario' => '7', 'Sigla' => 'maria.s']],
            ],
            'Interessados' => (object) ['Nome' => 'João da Silva'],
            'AndamentoGeracao' => (object) ['IdAndamento' => '1', 'DataHora' => '17/11/2026 09:12:00', 'Descricao' => 'Processo gerado', 'Unidade' => (object) ['Sigla' => 'SEAD-PROT']],
            'UltimoAndamento' => (object) ['IdAndamento' => '3', 'DataHora' => '18/11/2026 14:02:00', 'Descricao' => 'Processo recebido na unidade <a href="x" onclick="alert(1)">SEAD-RH</a>'],
        ];
    }

    public function test_acao_na_tabela_mostra_o_processo(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->seiFalso()->respond('consultarProcedimento', self::processo());
        $processo = Processo::query()->create(['protocolo_sei' => '53000.012345/2026-11']);

        Livewire::test(ListProcessos::class)
            ->mountTableAction(ConsultarProcessoSeiAction::NOME, $processo)
            ->assertSee('Processo 53000.012345/2026-11')
            ->assertSee('Pessoal: Férias')
            ->assertSee('Restrito')
            ->assertSee('maria.s')
            ->assertSee('João da Silva')
            ->assertSee('Abrir no SEI')
            ->assertSee('Processo recebido na unidade SEAD-RH')
            ->assertDontSee('onclick="alert(1)"', false);

        $fake->assertCalled('consultarProcedimento', fn (array $args) => $args['ProtocoloProcedimento'] === '53000.012345/2026-11'
            && $args['SinRetornarUltimoAndamento'] === 'S');
    }

    public function test_some_sem_protocolo(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $processo = Processo::query()->create(['protocolo_sei' => null]);

        Livewire::test(ListProcessos::class)->assertTableActionHidden(ConsultarProcessoSeiAction::NOME, $processo);
    }

    public function test_cabecalho_lista_andamentos_das_tarefas_e_mostra_erro(): void
    {
        $this->actingAs($this->usuario('Ana'));
        $fake = $this->seiFalso()
            ->respond('consultarProcedimento', self::processo())
            ->respond('listarAndamentos', [(object) ['IdAndamento' => '2', 'DataHora' => '18/11/2026 11:30:00', 'Descricao' => 'Processo remetido']]);
        $processo = Processo::query()->create(['protocolo_sei' => '53000.012345/2026-11']);

        Livewire::test(ViewProcesso::class, ['record' => $processo->getRouteKey()])
            ->mountAction(ConsultarProcessoSeiAction::NOME)
            ->assertSeeInOrder(['Processo recebido', 'Processo remetido', 'Processo gerado']);

        $fake->assertCalled('listarAndamentos');

        $fake->respond('consultarProcedimento', new FaultException('Processo não encontrado.', 'consultarProcedimento'));

        Livewire::test(ViewProcesso::class, ['record' => $processo->getRouteKey()])
            ->mountAction(ConsultarProcessoSeiAction::NOME)
            ->assertSee('O SEI recusou a operação: Processo não encontrado.');
    }
}
