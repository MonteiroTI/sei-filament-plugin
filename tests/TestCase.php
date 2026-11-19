<?php

namespace MonteiroTI\SeiFilament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Composer\InstalledVersions;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\SeiServiceProvider;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;
use MonteiroTI\SeiFilament\SeiFilamentServiceProvider;
use MonteiroTI\SeiFilament\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->json('permissoes')->nullable();
            $table->timestamps();
        });

        Schema::create('processos_teste', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo_sei')->nullable();
            $table->timestamps();
        });

        foreach (['create_sei_conexoes_table', 'create_sei_logs_table'] as $migration) {
            (require dirname(__DIR__)."/database/migrations/{$migration}.php.stub")->up();
        }

        // Permissão = nome na lista do usuário (sem pacote de permissões).
        Gate::before(fn (User $user, string $ability) => in_array($ability, $user->permissoes ?? [], true) ? true : null);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LivewireServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            SeiServiceProvider::class,
            SeiFilamentServiceProvider::class,
            Fixtures\AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);

        // Conexão do "config/sei.php" (.env) do app de teste.
        $app['config']->set('sei.connections.default', [
            'wsdl' => 'https://sei.teste.dev/sei/controlador_ws.php?servico=sei',
            'sigla_sistema' => 'SISTEMA',
            'identificacao_servico' => 'chave-do-env',
            'id_unidade' => '110000001',
            'parametros' => ['series' => ['oficio' => '12']],
        ]);

        $views = sys_get_temp_dir().'/sei-filament-views-'.InstalledVersions::getVersion('filament/filament');
        is_dir($views) || mkdir($views, 0777, true);
        $app['config']->set('view.compiled', $views);
    }

    protected function seiFalso(): SeiFake
    {
        return Sei::fake();
    }

    /**
     * @param  list<string>  $permissoes
     */
    protected function usuario(string $nome = 'Ana', array $permissoes = []): User
    {
        return User::query()->create([
            'name' => $nome,
            'email' => strtolower($nome).'@teste.dev',
            'password' => 'x',
            'permissoes' => $permissoes,
        ]);
    }

    /**
     * @return list<object>
     */
    protected static function unidades(): array
    {
        return [
            (object) ['IdUnidade' => '110000001', 'Sigla' => 'SEAD-GAB', 'Descricao' => 'Gabinete', 'SinProtocolo' => 'S', 'SinArquivamento' => 'N', 'SinOuvidoria' => 'N'],
            (object) ['IdUnidade' => '110000002', 'Sigla' => 'SEAD-PROT', 'Descricao' => 'Protocolo Geral', 'SinProtocolo' => 'S', 'SinArquivamento' => 'S', 'SinOuvidoria' => 'N'],
        ];
    }
}
