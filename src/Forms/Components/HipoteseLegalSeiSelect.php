<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Hipóteses legais (listarHipotesesLegais). */
class HipoteseLegalSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'hipoteses_legais';
    }
}
