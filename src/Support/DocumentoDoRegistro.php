<?php

namespace MonteiroTI\SeiFilament\Support;

use Closure;
use Filament\Actions\MountableAction;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;

/**
 * Número do documento no SEI para o registro de uma ação: o da Closure do sistema, o documento_formatado do
 * SeiDocumento ou o atributo documento_sei.
 */
final class DocumentoDoRegistro
{
    public static function protocolo(MountableAction $acao, ?Closure $documento, ?Model $record): ?string
    {
        $valor = $documento !== null
            ? $acao->evaluate($documento, ['record' => $record])
            : ($record instanceof SeiDocumento ? $record->documento_formatado : $record?->getAttribute('documento_sei'));

        return is_scalar($valor) && trim((string) $valor) !== '' ? trim((string) $valor) : null;
    }

    /** Conexão do vínculo, quando o registro é um SeiDocumento. */
    public static function conexao(?Model $record): ?string
    {
        return $record instanceof SeiDocumento ? $record->conexao : null;
    }
}
