<?php

namespace MonteiroTI\SeiFilament\Tests;

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Dto\Assinatura;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiAssinado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\NovoAndamentoSei;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiTramitado;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTI\SeiFilament\Notifications\AvisoSeiNotification;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\Pages\ListProcessosSei;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\SeiFilamentPlugin;
use MonteiroTI\SeiFilament\Tests\Fixtures\Processo;

class AvisosTest extends TestCase
{
    private function processo(): SeiProcesso
    {
        $registro = Processo::query()->create();

        return SeiProcesso::query()->create([
            'conexao' => 'default', 'id_procedimento' => '77', 'protocolo_formatado' => '53000.012345/2026-11',
            'link_acesso' => 'https://sei.teste.dev/p/77',
            'vinculavel_type' => Processo::class, 'vinculavel_id' => $registro->getKey(),
            'unidades_abertas' => [['id' => '2', 'sigla' => 'SEAD-RH', 'usuario' => null]],
        ]);
    }

    public function test_desligado_nao_avisa(): void
    {
        Notification::fake();
        event(new ProcessoSeiTramitado($this->processo(), ['1'], ['2']));

        Notification::assertNothingSent();
    }

    public function test_avisa_os_destinatarios_com_link_para_o_processo(): void
    {
        Notification::fake();
        $ana = $this->usuario('Ana');
        SeiFilamentPlugin::get()->avisos()->destinatariosDosAvisos(fn (SeiProcesso $processo, string $tipo) => [$ana, null, $ana]);
        $processo = $this->processo();

        event(new ProcessoSeiTramitado($processo, ['1'], ['2']));

        Notification::assertSentToTimes($ana, AvisoSeiNotification::class, 1);
        Notification::assertSentTo($ana, function (AvisoSeiNotification $aviso) use ($ana, $processo) {
            $dados = $aviso->toDatabase($ana);

            return $aviso->tipo === 'tramitado'
                && $dados['title'] === 'Processo tramitado'
                && str_contains($dados['body'], 'Processo 53000.012345/2026-11 (Processo #')
                && str_contains($dados['body'], 'Agora aberto em: SEAD-RH.')
                && $dados['actions'][0]['url'] === ProcessoSeiResource::getUrl('view', ['record' => $processo])
                && $dados['actions'][1]['url'] === 'https://sei.teste.dev/p/77';
        });
    }

    public function test_so_os_tipos_escolhidos(): void
    {
        Notification::fake();
        $ana = $this->usuario('Ana');
        SeiFilamentPlugin::get()->avisos(tipos: ['assinado'])->destinatariosDosAvisos(fn () => [$ana]);
        $processo = $this->processo();
        $documento = SeiDocumento::query()->create(['sei_processo_id' => $processo->id, 'conexao' => 'default', 'id_documento' => '1', 'documento_formatado' => '0012345']);

        event(new NovoAndamentoSei($processo, new Andamento('9', descricao: 'Processo recebido')));
        event(new DocumentoSeiAssinado($documento, [new Assinatura('Maria Souza')]));

        Notification::assertSentToTimes($ana, AvisoSeiNotification::class, 1);
        Notification::assertSentTo($ana, fn (AvisoSeiNotification $aviso) => $aviso->titulo === 'Documento 0012345 assinado'
            && str_contains($aviso->corpo, 'Assinado por Maria Souza.'));
    }

    public function test_falha_da_sincronizacao_avisa(): void
    {
        Notification::fake();
        $ana = $this->usuario('Ana', [ProcessoSeiResource::PERMISSAO]);
        SeiFilamentPlugin::get()->avisos()->destinatariosDosAvisos(fn () => [$ana]);
        $processo = $this->processo();
        $this->seiFalso()->respond('consultarProcedimento', new FaultException('Processo não encontrado.', 'consultarProcedimento'));
        $this->actingAs($ana);

        Livewire::test(ListProcessosSei::class)->callTableAction('sincronizar', $processo);

        Notification::assertSentTo($ana, fn (AvisoSeiNotification $aviso) => $aviso->tipo === 'falha'
            && str_contains($aviso->corpo, 'Processo não encontrado.'));
    }

    public function test_sem_spatie_e_sem_destinatarios_ninguem_recebe(): void
    {
        Notification::fake();
        SeiFilamentPlugin::get()->avisos();

        event(new ProcessoSeiTramitado($this->processo(), ['1'], ['2']));

        Notification::assertNothingSent();
    }
}
