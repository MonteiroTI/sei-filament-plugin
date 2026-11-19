<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Tipos de prioridade (listarTiposPrioridade). */
class TipoPrioridadeSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'tipos_prioridade';
    }
}
