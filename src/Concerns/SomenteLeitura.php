<?php

namespace MonteiroTI\SeiFilament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resource de registros que o plugin grava sozinho: não são criados, editados nem excluídos pela tela.
 */
trait SomenteLeitura
{
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }
}
