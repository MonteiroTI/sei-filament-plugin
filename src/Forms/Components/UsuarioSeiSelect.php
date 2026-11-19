<?php

namespace MonteiroTI\SeiFilament\Forms\Components;

/** Usuários da unidade (listarUsuarios). */
class UsuarioSeiSelect extends CatalogoSeiSelect
{
    protected function catalogo(): string
    {
        return 'usuarios';
    }
}
