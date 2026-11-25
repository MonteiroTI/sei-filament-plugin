# SEI no Filament

Plugin do Filament 3 para o [conector do SEI](https://github.com/MonteiroTI/sei-integration-php) (`monteiroti/sei-integration`).

| Tela | O que faz | Permissão padrão |
|---|---|---|
| **Conexões** | Cadastro das conexões do SEI (e do SIP) no banco, com a chave criptografada, e o botão **Testar** (o mesmo do `sei:ping`) | `editar_configuracao:sei` |
| **Catálogos** | Unidades, tipos de documento, tipos de processo, hipóteses legais, marcadores, usuários... por conexão e unidade, com busca e **Limpar cache** | `consultar_catalogos:sei` |
| **Log das chamadas** | Cada chamada ao SEI/SIP, com tempo, erro e argumentos (mascarados pelo conector) | `ver_log:sei` |
| **Widget** | No dashboard: último teste da conexão padrão, falhas e tempo médio nas últimas 24 h | quem configura ou vê o log |
| **Processos vinculados** (com `->vinculos()`) | Processos e documentos que o sistema gerou ou acompanha, com **Consultar no SEI** e **Sincronizar** | `ver_vinculos:sei` |
| **Operações** (com `->vinculos()`) | Operações com idempotência; as incertas aparecem no menu e são resolvidas na tela | `ver_vinculos:sei`; resolver: `resolver_operacoes:sei` |

| Peça para as telas do sistema | Uso |
|---|---|
| `ConsultarProcessoSeiAction` | Slide-over com o processo no SEI: tipo, nível de acesso, onde está aberto, interessados, link e andamentos |
| `UnidadeSeiSelect`, `SerieSeiSelect`, `TipoProcessoSeiSelect`, `HipoteseLegalSeiSelect`, `MarcadorSeiSelect`, `UsuarioSeiSelect`, `TipoConferenciaSeiSelect`, `TipoPrioridadeSeiSelect` | Selects com as opções do catálogo do SEI |
| `Support\Rotulos` | Rótulo e cor em pt-BR dos enums do conector (`NivelAcesso`, `EstadoBloco`...) |
| `Support\Erros` | Exceções do conector em notificação do Filament |

## Versões

| Plugin | Filament | Laravel | Conector |
|---|---|---|---|
| 1.x (`main`) | 3.3 | 12 | `dev-main` (até a v1.0 do conector) |

## Instalação

Os dois repositórios são privados: o `composer.json` do sistema precisa dos dois e de um token do GitHub com acesso a eles (`composer config --global github-oauth.github.com <token>`).

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/MonteiroTI/sei-filament-plugin" },
    { "type": "vcs", "url": "https://github.com/MonteiroTI/sei-integration-php" }
]
```

```bash
composer require monteiroti/sei-filament-plugin:^1.0@dev monteiroti/sei-integration:dev-main
php artisan vendor:publish --tag=sei-filament-migrations
php artisan migrate
```

No painel:

```php
use MonteiroTI\SeiFilament\SeiFilamentPlugin;

$panel->plugin(SeiFilamentPlugin::make());
```

## Opções

```php
SeiFilamentPlugin::make()
    ->navigationGroup('Integrações')           // padrão: "SEI"
    ->conexoes()                               // tela de conexões
    ->catalogos()                              // tela de catálogos
    ->logs(dias: 30)                           // log das chamadas; registros mais velhos são apagados aos poucos
    ->widget(false)                            // sem o widget no dashboard
    ->vinculos()                               // processos vinculados e operações (desligado por padrão)
    ->autorizarConfiguracao(fn (User $user) => $user->isAdmin())
    ->autorizarCatalogos(fn (User $user) => true)
    ->autorizarLogs(fn (User $user) => $user->isAdmin())
    ->autorizarVinculos(fn (User $user) => true)
    ->autorizarResolverOperacoes(fn (User $user) => $user->isAdmin());
```

Tudo vem ligado, menos os vínculos. Sem `autorizarX`, cada tela pergunta ao `Gate` pela permissão da tabela acima (constante `PERMISSAO` da classe), o que funciona com o spatie/laravel-permission ou com uma policy/gate do sistema.

### Subclasse do app no lugar de uma tela

Cada tela aceita uma subclasse, por exemplo para implementar o `HasShieldPermissions` do Shield ou mudar colunas:

```php
SeiFilamentPlugin::make()
    ->conexoes(resource: App\Filament\Resources\ConexaoSeiResource::class)
    ->logs(resource: App\Filament\Resources\SeiLogResource::class)
    ->catalogos(pagina: App\Filament\Pages\CatalogosSei::class)
    ->vinculos(processos: App\Filament\Resources\ProcessoSeiResource::class, operacoes: App\Filament\Resources\OperacaoSeiResource::class);
```

A subclasse precisa estender a classe do plugin.

## Conexões no banco

As conexões cadastradas na tela entram em `config('sei.connections.{nome}')` (e `sei.sip.connections.{nome}` com o SIP preenchido) **por cima** do `config/sei.php`:

- Mesmo nome de uma conexão do config (ex.: `default`) troca os dados dela; o que o banco não tem (ex.: `parametros`) continua vindo do config.
- **Conexão padrão** vira o `sei.default`: é a usada por `Sei::...` sem `connection()`.
- Desativar ou excluir faz voltar a valer o config/.env.
- As chaves (`identificacao_servico`, `sip_chave_acesso`) ficam criptografadas com a `APP_KEY` e nunca voltam para o navegador: na edição, em branco mantém a atual.
- O banco só é lido quando o SEI é usado no processo, e uma vez só. **Workers de fila** (e Octane) leem na primeira chamada e guardam: depois de mudar uma conexão, rode `php artisan queue:restart`.

**Testar** carrega a WSDL, confere as operações do conector com ela e valida as credenciais listando as unidades liberadas para o serviço (sem cache). O resultado fica gravado na conexão e aparece no widget.

## Catálogos

A tela consulta os catálogos com o cache do conector (`sei.cache`). Para unidades e usuários vale a unidade escolhida (`naUnidade`); em tipos de documento, de processo, de conferência e de prioridade ela filtra o catálogo. Clique no ID para copiar.

**Limpar cache** vale para todas as conexões: o conector não tem flush, então o plugin versiona o prefixo (`sei.cache.prefix` passa a `sei:v2`, `sei:v3`...) e as chaves antigas expiram sozinhas.

## Log das chamadas

Gravado pelos eventos do conector (`sei.events`, ligado por padrão), inclusive das chamadas da fila e do agendador. Grava operação, conexão, tempo, usuário, erro e os argumentos como o conector os registra: chave de acesso e dados pessoais mascarados (`sei.log.dados_pessoais`), Base64 resumido. O retorno do SEI não é gravado.

Com `->logs(false)` a tela some e nada é gravado.

## Processos vinculados e operações

Telas do módulo de persistência do conector (`Sei::integracao()`), que é opcional: publique as migrations dele antes de ligar.

```bash
php artisan vendor:publish --tag=sei-migrations
php artisan migrate
```

```php
SeiFilamentPlugin::make()->vinculos();
```

- **Processos vinculados**: processo, registro do sistema (com link para a tela dele, se houver um resource), nível de acesso, situação, onde está aberto e a última sincronização. Na tela do processo, os documentos com assinatura e publicação.
  - **Sincronizar** faz o mesmo que o `sei:sincronizar` com o processo, na hora, e religa a sincronização que o conector desligou por erro.
- **Operações**: as chamadas com idempotência (`gerarProcesso`, `incluirDocumento`...). Uma operação **incerta** (timeout depois do envio: o SEI pode ou não ter executado) trava novas tentativas com a mesma chave; o menu mostra quantas há.
  - **Resolver** faz o mesmo que o `sei:operacoes:resolver`, depois de conferir no SEI: informar o processo ou documento que o SEI criou (vira vínculo), marcar como executada, ou descartar para liberar nova tentativa.

Os models seguem `sei.persistencia.models` do conector, inclusive subclasses do sistema.

## Consultar processo

```php
use MonteiroTI\SeiFilament\Actions\ConsultarProcessoSeiAction;

// Cabeçalho da página (Filament\Actions)
protected function getHeaderActions(): array
{
    return [ConsultarProcessoSeiAction::make()];
}

// Tabela e infolist: no Filament 3 cada lugar tem a sua classe de ação
->actions([ConsultarProcessoSeiAction::tabela(protocolo: fn (Processo $record) => $record->numero_sei)])
InfolistActions::make([ConsultarProcessoSeiAction::infolist(conexao: 'homologacao')])
```

- Sem `protocolo`, a ação usa o processo vinculado pelo `HasSeiProcesso` do conector (`$record->seiProcesso`, com a conexão do vínculo) ou o atributo `protocolo_sei`. Sem protocolo, a ação some.
- Mostra a geração, a conclusão e o último andamento. Para listar mais, passe as tarefas: `ConsultarProcessoSeiAction::make(tarefas: [1, 32])`.
- Processo sigiloso o SEI não devolve; o erro aparece no slide-over.

## Selects

```php
use MonteiroTI\SeiFilament\Forms\Components\SerieSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;

UnidadeSeiSelect::make('id_unidade')->live(),
SerieSeiSelect::make('id_serie')
    ->unidade(fn (Get $get) => $get('id_unidade'))
    ->conexao('homologacao')
    ->required(),
```

As opções vêm do cache do conector. Com o SEI fora do ar, ou a operação não liberada na conexão, o select fica vazio e mostra o motivo no hint; o formulário abre normalmente.

## Rótulos dos enums

```php
use MonteiroTI\SeiFilament\Support\Rotulos;
use MonteiroTi\Sei\Enum\NivelAcesso;

Select::make('nivel_acesso')->options(Rotulos::opcoes(NivelAcesso::class));

TextColumn::make('nivel')
    ->badge()
    ->formatStateUsing(fn (NivelAcesso $state) => Rotulos::de($state))
    ->color(fn (NivelAcesso $state) => Rotulos::cor($state));
```

## Testes

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

Os testes usam o `Sei::fake()` do conector: nada sai para a rede.
