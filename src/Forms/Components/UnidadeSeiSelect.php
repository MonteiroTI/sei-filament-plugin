<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Unidades liberadas para o serviço (listarUnidades). */
class UnidadeSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'unidades';
    }
}
