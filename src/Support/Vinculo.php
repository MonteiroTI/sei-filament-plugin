<?php

namespace MonteiroTI\SeiFilament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * O registro do sistema ligado a um processo ou operação do SEI (morph "vinculavel"): texto e link para a tela dele.
 */
final class Vinculo
{
    public static function texto(?string $tipo, int|string|null $id): ?string
    {
        if ($tipo === null || $id === null) {
            return null;
        }

        return class_basename(Relation::getMorphedModel($tipo) ?? $tipo).' #'.$id;
    }

    /** URL da tela do registro no painel atual (view, senão edit), se houver um resource para o model. */
    public static function url(?Model $registro): ?string
    {
        if ($registro === null || ($painel = Filament::getCurrentPanel()) === null) {
            return null;
        }

        $resource = $painel->getModelResource($registro);
        if ($resource === null) {
            return null;
        }

        foreach (['view', 'edit'] as $pagina) {
            if ($resource::hasPage($pagina) && $resource::can($pagina, $registro)) {
                return $resource::getUrl($pagina, ['record' => $registro]);
            }
        }

        return null;
    }
}
