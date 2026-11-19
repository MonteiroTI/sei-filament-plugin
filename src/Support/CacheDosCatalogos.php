<?php

namespace MonteiroTI\SeiFilament\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * O conector guarda os catálogos no cache sem tags nem flush. Para limpar, o plugin versiona o prefixo
 * (sei.cache.prefix = "{prefixo}:v{n}"): trocar a versão deixa as chaves antigas para expirar sozinhas.
 */
final class CacheDosCatalogos
{
    public const CHAVE_DA_VERSAO = 'sei-filament:catalogos:versao';

    public static function versao(): int
    {
        return (int) self::store()->get(self::CHAVE_DA_VERSAO, 1);
    }

    public static function aplicarPrefixo(): void
    {
        $original = config('sei.cache.prefixo_original') ?? config('sei.cache.prefix', 'sei');
        config()->set('sei.cache.prefixo_original', $original);
        config()->set('sei.cache.prefix', $original.':v'.self::versao());
    }

    public static function limpar(): void
    {
        self::store()->forever(self::CHAVE_DA_VERSAO, self::versao() + 1);
        self::aplicarPrefixo();

        // O prefixo entra no cliente quando ele é criado.
        if (app()->resolved(SeiManager::class)) {
            app(SeiManager::class)->purge();
        }
    }

    private static function store(): Repository
    {
        return Cache::store(config('sei.cache.store'));
    }
}
