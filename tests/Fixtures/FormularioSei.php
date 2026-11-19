<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use MonteiroTI\SeiFilament\Forms\Components\SerieSeiSelect;
use MonteiroTI\SeiFilament\Forms\Components\UnidadeSeiSelect;

/** Formulário do app com os selects do plugin. */
class FormularioSei extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            UnidadeSeiSelect::make('id_unidade'),
            SerieSeiSelect::make('id_serie')->unidade('110000002'),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
