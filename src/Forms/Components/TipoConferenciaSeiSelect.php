<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Tipos de conferência de documento externo (listarTiposConferencia). */
class TipoConferenciaSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'tipos_conferencia';
    }
}
