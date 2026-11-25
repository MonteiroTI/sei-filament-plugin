<?php

namespace MonteiroTI\SeiFilament\Support;

use Illuminate\Database\QueryException;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTI\SeiFilament\Models\ConexaoSei;

/**
 * Leva as conexões do banco para config('sei.*'): o conector só lê conexões do config e não tem extend(), então o
 * plugin escreve no config e descarta os clientes já criados (purge).
 *
 * O que não está no banco continua vindo do config/sei.php (ex.: os parametros de uma conexão de mesmo nome).
 */
final class ConexoesNoConfig
{
    private const ORIGINAL = 'sei-filament.config_original';

    public static function aplicar(): void
    {
        CacheDosCatalogos::aplicarPrefixo();

        try {
            $conexoes = ConexaoSei::query()->where('ativo', true)->orderBy('nome')->get();
        } catch (QueryException) {
            // Antes da migration (ou sem banco, como no package:discover do composer): fica o config/sei.php.
            return;
        }

        // Sempre a partir do config/sei.php: conexão excluída ou desativada no banco sai do config.
        $original = config(self::ORIGINAL) ?? [
            'default' => config('sei.default'),
            'connections' => (array) config('sei.connections', []),
            'sip.default' => config('sei.sip.default'),
            'sip.connections' => (array) config('sei.sip.connections', []),
        ];
        config()->set(self::ORIGINAL, $original);

        foreach ($original as $chave => $valor) {
            config()->set("sei.{$chave}", $valor);
        }

        foreach ($conexoes as $conexao) {
            $chave = "sei.connections.{$conexao->nome}";
            $atual = (array) config($chave, []);
            config()->set($chave, [
                ...$atual,
                ...$conexao->paraConfigSei(),
                // Parâmetro a parâmetro: os do banco valem por cima dos do config.
                'parametros' => array_replace_recursive((array) ($atual['parametros'] ?? []), $conexao->parametros ?? []),
            ]);

            if ($conexao->temSip()) {
                $chave = "sei.sip.connections.{$conexao->nome}";
                config()->set($chave, array_merge((array) config($chave, []), $conexao->paraConfigSip()));
            }

            if ($conexao->padrao) {
                config()->set('sei.default', $conexao->nome);

                if ($conexao->temSip()) {
                    config()->set('sei.sip.default', $conexao->nome);
                }
            }
        }
    }

    /** Depois de salvar ou excluir uma conexão: vale já, sem esperar o próximo processo. */
    public static function recarregar(): void
    {
        if (! app()->resolved(SeiManager::class)) {
            return;
        }

        self::aplicar();
        app(SeiManager::class)->purge();
    }

    /**
     * Nomes das conexões SEI disponíveis (banco + config), a padrão primeiro.
     *
     * @return array<string, string>
     */
    public static function nomes(): array
    {
        $manager = app(SeiManager::class);
        $padrao = $manager->getDefaultConnection();
        $nomes = array_keys((array) config('sei.connections', []));
        usort($nomes, fn ($a, $b) => [$a !== $padrao, $a] <=> [$b !== $padrao, $b]);

        return array_combine($nomes, array_map(fn ($nome) => $nome === $padrao ? "{$nome} (padrão)" : $nome, $nomes));
    }

    /**
     * Nomes das conexões SIP (banco + config), a padrão primeiro.
     *
     * @return array<string, string>
     */
    public static function nomesSip(): array
    {
        app(SeiManager::class);
        $padrao = (string) config('sei.sip.default', 'default');
        $nomes = array_keys(array_filter((array) config('sei.sip.connections', []), fn ($c) => is_array($c) && filled($c['wsdl'] ?? null)));
        usort($nomes, fn ($a, $b) => [$a !== $padrao, $a] <=> [$b !== $padrao, $b]);

        return array_combine($nomes, array_map(fn ($nome) => $nome === $padrao ? "{$nome} (padrão)" : $nome, $nomes));
    }
}
