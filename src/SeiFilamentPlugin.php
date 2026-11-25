<?php

namespace MonteiroTI\SeiFilament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use MonteiroTI\SeiFilament\Pages\CatalogosSei;
use MonteiroTI\SeiFilament\Resources\ConexoesSei\ConexaoSeiResource;
use MonteiroTI\SeiFilament\Resources\OperacoesSei\OperacaoSeiResource;
use MonteiroTI\SeiFilament\Resources\ProcessosSei\ProcessoSeiResource;
use MonteiroTI\SeiFilament\Resources\SeiLogs\SeiLogResource;
use MonteiroTI\SeiFilament\Widgets\SituacaoSeiWidget;

/**
 * Telas do conector do SEI num painel do Filament:
 *
 * ```php
 * $panel->plugin(SeiFilamentPlugin::make()->navigationGroup('Integrações')->logs(dias: 30));
 * ```
 *
 * Tudo vem ligado. Cada tela pede a sua permissão (constante PERMISSAO da classe; troque com autorizarX) e aceita uma
 * subclasse do app no lugar da do plugin (ex.: para implementar o HasShieldPermissions do Shield):
 *
 * ```php
 * SeiFilamentPlugin::make()->conexoes(resource: App\Filament\Resources\ConexaoSeiResource::class);
 * ```
 */
class SeiFilamentPlugin implements Plugin
{
    public const ID = 'sei';

    private bool $conexoes = true;

    private bool $catalogos = true;

    private bool $logs = true;

    private int $diasDeLog = 90;

    private bool $widget = true;

    private bool $vinculos = false;

    /**
     * Subclasses do app no lugar das telas do plugin, pela classe do plugin.
     *
     * @var array<class-string, class-string>
     */
    private array $classes = [];

    private string|Closure|null $navigationGroup = 'SEI';

    private ?Closure $autorizarConfiguracao = null;

    private ?Closure $autorizarCatalogos = null;

    private ?Closure $autorizarLogs = null;

    private ?Closure $autorizarVinculos = null;

    private ?Closure $autorizarResolverOperacoes = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(self::ID);
    }

    /**
     * O plugin do painel atual ou, fora de um painel (fila, agendador, console), o do primeiro painel que o tem.
     */
    public static function configurado(): ?static
    {
        $atual = Filament::getCurrentPanel();
        $paineis = $atual?->hasPlugin(self::ID) ? [$atual] : Filament::getPanels();

        foreach ($paineis as $painel) {
            if ($painel->hasPlugin(self::ID)) {
                /** @var static */
                return $painel->getPlugin(self::ID);
            }
        }

        return null;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources(array_values(array_filter([
                $this->conexoes ? $this->recursoDeConexoes() : null,
                $this->logs ? $this->recursoDeLogs() : null,
                $this->vinculos ? $this->recursoDeProcessos() : null,
                $this->vinculos ? $this->recursoDeOperacoes() : null,
            ])))
            ->pages(array_values(array_filter([
                $this->catalogos ? $this->paginaDeCatalogos() : null,
            ])))
            ->widgets(array_values(array_filter([
                $this->widget ? SituacaoSeiWidget::class : null,
            ])));
    }

    public function boot(Panel $panel): void {}

    /**
     * Conexões do SEI guardadas no banco (sei_conexoes), com o teste de conexão. Valem por cima do config/sei.php.
     *
     * @param  class-string<ConexaoSeiResource>|null  $resource  subclasse do app no lugar da do plugin
     */
    public function conexoes(bool $ligado = true, ?string $resource = null): static
    {
        $this->conexoes = $ligado;
        $this->usarClasse(ConexaoSeiResource::class, $resource);

        return $this;
    }

    /**
     * Consulta dos catálogos do SEI (unidades, séries, tipos de processo...) e limpeza do cache deles.
     *
     * @param  class-string<CatalogosSei>|null  $pagina  subclasse do app no lugar da do plugin
     */
    public function catalogos(bool $ligado = true, ?string $pagina = null): static
    {
        $this->catalogos = $ligado;
        $this->usarClasse(CatalogosSei::class, $pagina);

        return $this;
    }

    /**
     * Log das chamadas ao SEI (sei_logs). Desligado, a tela some e nada é gravado. Registros com mais de $dias dias
     * são apagados aos poucos, durante a gravação.
     *
     * @param  class-string<SeiLogResource>|null  $resource  subclasse do app no lugar da do plugin
     */
    public function logs(bool $ligado = true, ?string $resource = null, int $dias = 90): static
    {
        $this->logs = $ligado;
        $this->diasDeLog = max(1, $dias);
        $this->usarClasse(SeiLogResource::class, $resource);

        return $this;
    }

    /**
     * Processos e documentos vinculados e operações com idempotência (módulo de persistência do conector:
     * php artisan vendor:publish --tag=sei-migrations). Vem desligado porque as tabelas são opcionais no conector.
     *
     * @param  class-string<ProcessoSeiResource>|null  $processos  subclasse do app no lugar da do plugin
     * @param  class-string<OperacaoSeiResource>|null  $operacoes  subclasse do app no lugar da do plugin
     */
    public function vinculos(bool $ligado = true, ?string $processos = null, ?string $operacoes = null): static
    {
        $this->vinculos = $ligado;
        $this->usarClasse(ProcessoSeiResource::class, $processos);
        $this->usarClasse(OperacaoSeiResource::class, $operacoes);

        return $this;
    }

    /** Widget da situação do SEI no dashboard do painel. */
    public function widget(bool $ligado = true): static
    {
        $this->widget = $ligado;

        return $this;
    }

    public function navigationGroup(string|Closure|null $grupo): static
    {
        $this->navigationGroup = $grupo;

        return $this;
    }

    /**
     * Quem cadastra, edita e testa as conexões. Padrão: Gate com ConexaoSeiResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarConfiguracao(Closure $regra): static
    {
        $this->autorizarConfiguracao = $regra;

        return $this;
    }

    /**
     * Quem consulta os catálogos. Padrão: Gate com CatalogosSei::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarCatalogos(Closure $regra): static
    {
        $this->autorizarCatalogos = $regra;

        return $this;
    }

    /**
     * Quem vê o log das chamadas. Padrão: Gate com SeiLogResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarLogs(Closure $regra): static
    {
        $this->autorizarLogs = $regra;

        return $this;
    }

    /**
     * Quem vê os processos vinculados e as operações (e sincroniza um processo). Padrão: Gate com
     * ProcessoSeiResource::PERMISSAO.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarVinculos(Closure $regra): static
    {
        $this->autorizarVinculos = $regra;

        return $this;
    }

    /**
     * Quem resolve uma operação incerta depois de conferir no SEI. Padrão: Gate com OperacaoSeiResource::PERMISSAO_RESOLVER.
     *
     * @param  Closure(Authenticatable): bool  $regra
     */
    public function autorizarResolverOperacoes(Closure $regra): static
    {
        $this->autorizarResolverOperacoes = $regra;

        return $this;
    }

    public function temVinculos(): bool
    {
        return $this->vinculos;
    }

    public function temLogs(): bool
    {
        return $this->logs;
    }

    public function diasDeLog(): int
    {
        return $this->diasDeLog;
    }

    public function temConexoes(): bool
    {
        return $this->conexoes;
    }

    /** @return class-string<ConexaoSeiResource> */
    public function recursoDeConexoes(): string
    {
        return $this->classes[ConexaoSeiResource::class] ?? ConexaoSeiResource::class;
    }

    /** @return class-string<SeiLogResource> */
    public function recursoDeLogs(): string
    {
        return $this->classes[SeiLogResource::class] ?? SeiLogResource::class;
    }

    /** @return class-string<ProcessoSeiResource> */
    public function recursoDeProcessos(): string
    {
        return $this->classes[ProcessoSeiResource::class] ?? ProcessoSeiResource::class;
    }

    /** @return class-string<OperacaoSeiResource> */
    public function recursoDeOperacoes(): string
    {
        return $this->classes[OperacaoSeiResource::class] ?? OperacaoSeiResource::class;
    }

    /** @return class-string<CatalogosSei> */
    public function paginaDeCatalogos(): string
    {
        return $this->classes[CatalogosSei::class] ?? CatalogosSei::class;
    }

    /**
     * Classe registrada no painel (a do app, se houver) para uma tela do plugin.
     *
     * @template T
     *
     * @param  class-string<T>  $classe
     * @return class-string<T>
     */
    public static function classeEmUso(string $classe): string
    {
        return static::configurado()?->classes[$classe] ?? $classe;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup instanceof Closure ? ($this->navigationGroup)() : $this->navigationGroup;
    }

    public function podeConfigurar(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarConfiguracao, ConexaoSeiResource::PERMISSAO);
    }

    public function podeConsultarCatalogos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarCatalogos, CatalogosSei::PERMISSAO);
    }

    public function podeVerLogs(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarLogs, SeiLogResource::PERMISSAO);
    }

    public function podeVerVinculos(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarVinculos, ProcessoSeiResource::PERMISSAO);
    }

    public function podeResolverOperacoes(?Authenticatable $user): bool
    {
        return $this->pode($user, $this->autorizarResolverOperacoes, OperacaoSeiResource::PERMISSAO_RESOLVER);
    }

    private function pode(?Authenticatable $user, ?Closure $regra, string $permissao): bool
    {
        if ($user === null) {
            return false;
        }

        return $regra !== null ? (bool) $regra($user) : Gate::forUser($user)->allows($permissao);
    }

    private function usarClasse(string $base, ?string $classe): void
    {
        if ($classe === null) {
            return;
        }

        if (! is_a($classe, $base, true)) {
            throw new InvalidArgumentException("{$classe} precisa estender {$base}.");
        }

        $this->classes[$base] = $classe;
    }
}
