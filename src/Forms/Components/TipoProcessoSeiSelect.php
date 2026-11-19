<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Tipos de processo (listarTiposProcedimento). */
class TipoProcessoSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'tipos_processo';
    }
}
