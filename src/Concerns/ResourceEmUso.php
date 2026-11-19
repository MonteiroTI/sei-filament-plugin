<?php

namespace MonteiroTI\SeiFilament\Concerns;

use MonteiroTI\SeiFilament\SeiFilamentPlugin;

/**
 * Página de resource do plugin que também serve à subclasse do app (->logs(resource: ...)): o resource vem do plugin,
 * e as URLs, a autorização e o menu saem da classe registrada no painel.
 */
trait ResourceEmUso
{
    public static function getResource(): string
    {
        return SeiFilamentPlugin::classeEmUso(static::$resource);
    }
}
