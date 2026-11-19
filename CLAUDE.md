# sei-filament-plugin

Plugin do Filament 3 para o conector `monteiroti/sei-integration` (repositório irmão `../sei-integration-php`). O molde é o `../prodata-filament-plugin` (branch `1.x`).

## Convenções

- Tudo em português: classes, métodos, textos da tela e comentários. Comentários curtos que dizem o porquê.
- Sem arquivo de tradução: os textos ficam no código.
- Cada tela do plugin: liga/desliga no `SeiFilamentPlugin`, aceita subclasse do app (`usarClasse`/`classeEmUso`; páginas de resource usam `Concerns\ResourceEmUso`), autorização por `autorizarX(Closure)` com fallback no `Gate` com a constante `PERMISSAO`.
- No Filament 3 cada lugar tem a sua classe de ação: ações para as telas do app têm `make()`, `tabela()` e `infolist()` com a mesma configuração.
- O plugin não chama o SEI direto: sempre pelo `SeiManager` do conector. Erros do conector viram texto com `Support\Erros` (inclui `InvalidArgumentException`, que não estende `SeiException`).
- Migrations são `.php.stub` que pulam tabela existente; publicadas com a tag `sei-filament-migrations`.
- Nunca coloque chaves de acesso, tokens ou CPFs reais no código ou nos testes.
- `composer.lock` não é versionado. README muda junto com o código.

## Testes

PHPUnit 11 + Testbench, com `Sei::fake()` do conector (sem rede). Painel de teste em `tests/Fixtures/AdminPanelProvider.php`; permissões pelo `Gate::before` com a lista `permissoes` do usuário.

```bash
vendor/bin/phpunit
vendor/bin/pint --test
```

Commits curtos em português, sem prefixo.
