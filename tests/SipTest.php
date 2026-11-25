<?php

namespace MonteiroTI\SeiFilament\Tests;

use Livewire\Livewire;
use MonteiroTi\Sei\Core\Exceptions\ValidationException;
use MonteiroTI\SeiFilament\Actions\ReplicarUsuarioSipAction;
use MonteiroTI\SeiFilament\Pages\SipSei;

class SipTest extends TestCase
{
    public function test_exige_permissao(): void
    {
        $this->actingAs($this->usuario('Bia'));
        $this->get(SipSei::getUrl())->assertForbidden();
    }

    public function test_busca_e_retira_permissao(): void
    {
        $this->actingAs($this->usuario('Ana', [SipSei::PERMISSAO]));
        $fake = $this->seiFalso()
            ->respond('listarPermissao', [(object) [
                'IdSistema' => '100000100', 'IdUsuario' => '55', 'IdOrigemUsuario' => 'u-42', 'IdUnidade' => '110000002',
                'IdPerfil' => '3', 'DataInicial' => '01/01/2026', 'SinSubunidades' => 'N',
            ]])
            ->respond('replicarPermissao', true);

        Livewire::test(SipSei::class)
            ->assertSet('conexao', 'default')
            ->assertDontSee('Permissões (')
            ->set('filtro.id_origem_usuario', 'u-42')
            ->call('buscar')
            ->assertSee('Permissões (1)')
            ->assertSee('origem u-42')
            ->assertSee('01/01/2026 a sem fim')
            ->call('retirar', 0)
            ->assertNotified('Permissão retirada');

        $fake->assertCalled('listarPermissao', fn (array $args) => $args['IdSistema'] === '100000100' && $args['IdOrigemUsuario'] === 'u-42' && $args['ChaveAcesso'] === 'chave-sip-do-env');
        $fake->assertCalled('replicarPermissao', fn (array $args) => $args['Permissoes'][0]['StaOperacao'] === 'E'
            && $args['Permissoes'][0]['IdUsuario'] === '55'
            && $args['Permissoes'][0]['IdPerfil'] === '3');
    }

    public function test_concede_permissao(): void
    {
        $this->actingAs($this->usuario('Ana', [SipSei::PERMISSAO]));
        $fake = $this->seiFalso()->respond('replicarPermissao', true);

        Livewire::test(SipSei::class)
            ->callAction('conceder', ['id_origem_usuario' => 'u-42', 'id_unidade' => '110000002', 'id_perfil' => '3', 'data_inicial' => '2026-12-01', 'subunidades' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Permissão concedida');

        $fake->assertCalled('replicarPermissao', fn (array $args) => $args['Permissoes'][0]['StaOperacao'] === 'A'
            && $args['Permissoes'][0]['IdSistema'] === '100000100'
            && $args['Permissoes'][0]['DataInicial'] === '01/12/2026'
            && $args['Permissoes'][0]['SinSubunidades'] === 'S');
    }

    public function test_replica_usuario_e_mostra_os_erros_do_sip(): void
    {
        $this->actingAs($this->usuario('Ana', [SipSei::PERMISSAO]));
        $fake = $this->seiFalso()->respond('replicarUsuario', true, new ValidationException("Usuário u-43: sigla já usada.\nUsuário u-43: CPF inválido.", 'replicarUsuario'));

        $pagina = Livewire::test(SipSei::class)
            ->callAction(ReplicarUsuarioSipAction::NOME, ['operacao' => 'C', 'id_origem' => 'u-42', 'sigla' => 'ana.souza', 'nome' => 'Ana Souza', 'cpf' => '123.456.789-09', 'email' => 'ana@orgao.gov.br'])
            ->assertHasNoActionErrors()
            ->assertNotified('Usuário ana.souza: cadastrar no SIP');

        $fake->assertCalled('replicarUsuario', fn (array $args) => $args['Usuarios'][0]['StaOperacao'] === 'C'
            && $args['Usuarios'][0]['Cpf'] === '12345678909'
            && $args['SinConsiderarOrgao'] === 'N');

        $pagina->callAction(ReplicarUsuarioSipAction::NOME, ['operacao' => 'C', 'id_origem' => 'u-43'])
            ->assertNotified('Usuário não replicado no SIP');
    }
}
