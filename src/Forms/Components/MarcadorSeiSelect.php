<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Marcadores da unidade (listarMarcadoresUnidade). */
class MarcadorSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'marcadores';
    }
}
