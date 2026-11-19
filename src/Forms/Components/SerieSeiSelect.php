<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Tipos de documento (listarSeries). */
class SerieSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'series';
    }
}
