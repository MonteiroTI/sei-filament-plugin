<?php

namespace MonteiroTI\SeiFilament\Support;

use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/**
 * O vínculo do processo no SEI para um registro: o próprio SeiProcesso ou o processo mais recente do model do
 * sistema com HasSeiProcesso (na conexão pedida, se houver).
 */
final class ProcessoDoRegistro
{
    public static function de(?Model $registro, ?string $conexao = null): ?SeiProcesso
    {
        if ($registro instanceof SeiProcesso) {
            return $registro;
        }

        if ($registro === null || ! method_exists($registro, 'seiProcessos')) {
            return null;
        }

        /** @var SeiProcesso|null */
        return $registro->seiProcessos()
            ->when($conexao !== null, fn ($q) => $q->where('conexao', $conexao))
            ->latest('id')
            ->first();
    }
}
