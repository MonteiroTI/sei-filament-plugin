<?php

namespace MonteiroTI\SeiFilament\Tests;

use Illuminate\Support\Lottery;
use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTI\SeiFilament\Models\SeiLog;
use MonteiroTI\SeiFilament\Resources\SeiLogs\Pages\ListSeiLogs;
use MonteiroTI\SeiFilament\Resources\SeiLogs\Pages\ViewSeiLog;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

class LogTest extends TestCase
{
    public function test_grava_a_chamada_com_os_argumentos_mascarados(): void
    {
        $this->actingAs($ana = $this->usuario('Ana'));
        $this->seiFalso()->respond('listarUnidades', self::unidades());

        Sei::catalogos(cache: false)->unidades();

        $log = SeiLog::query()->sole();
        $this->assertSame('sei:default', $log->conexao);
        $this->assertSame('default', $log->nomeDaConexao());
        $this->assertSame('listarUnidades', $log->operacao);
        $this->assertTrue($log->sucesso);
        $this->assertSame((string) $ana->getKey(), $log->user_id);
        $this->assertSame('SISTEMA', $log->argumentos['SiglaSistema']);
        $this->assertSame('***', $log->argumentos['IdentificacaoServico']);
        $this->assertStringNotContainsString('chave-do-env', json_encode($log->argumentos));
    }

    public function test_grava_a_falha(): void
    {
        $this->seiFalso()->respond('consultarProcedimento', new FaultException("Processo não encontrado.\nVerifique o número.", 'consultarProcedimento', 'Server'));

        try {
            Sei::processos()->consultar('123');
        } catch (FaultException) {
        }

        $log = SeiLog::query()->sole();
        $this->assertFalse($log->sucesso);
        $this->assertSame(FaultException::class, $log->erro_classe);
        $this->assertSame('Server', $log->fault_code);
        $this->assertStringContainsString('Processo não encontrado', $log->erro_mensagem);
    }

    public function test_log_desligado_nao_grava(): void
    {
        SeiFilamentPlugin::get()->logs(false);
        $this->seiFalso()->respond('listarUnidades', self::unidades());

        Sei::catalogos(cache: false)->unidades();

        $this->assertSame(0, SeiLog::query()->count());
    }

    public function test_poda_os_registros_antigos(): void
    {
        SeiFilamentPlugin::get()->logs(dias: 30);
        SeiLog::query()->create(['conexao' => 'sei:default', 'operacao' => 'velha', 'sucesso' => true, 'duracao_ms' => 1, 'created_at' => now()->subDays(31)]);
        SeiLog::query()->create(['conexao' => 'sei:default', 'operacao' => 'recente', 'sucesso' => true, 'duracao_ms' => 1, 'created_at' => now()->subDays(29)]);
        $this->seiFalso()->respond('listarUnidades', self::unidades());

        Lottery::alwaysWin();
        try {
            Sei::catalogos(cache: false)->unidades();
        } finally {
            Lottery::determineResultNormally();
        }

        $this->assertSame(['recente', 'listarUnidades'], SeiLog::query()->orderBy('id')->pluck('operacao')->all());
    }

    public function test_tela_do_log(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(SeiLogResource::getUrl())->assertForbidden();

        $this->actingAs($this->usuario('Ana', [SeiLogResource::PERMISSAO]));
        $log = SeiLog::query()->create([
            'conexao' => 'sei:default', 'operacao' => 'incluirDocumento', 'sucesso' => false, 'duracao_ms' => 1400,
            'argumentos' => ['IdentificacaoServico' => '***', 'Documento' => ['IdSerie' => '999']],
            'erro_classe' => FaultException::class, 'erro_mensagem' => 'Série [999] não encontrada.',
        ]);

        Livewire::test(ListSeiLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('1,4 s')
            ->assertSee('Série [999] não encontrada.');

        Livewire::test(ViewSeiLog::class, ['record' => $log->getRouteKey()])
            ->assertSee('Documento.IdSerie')
            ->assertSee('999');
    }
}
