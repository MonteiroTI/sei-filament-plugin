{{-- Conexão e unidade da página (Concerns\EscolheConexaoEUnidade). $semUnidade esconde a unidade. --}}
<div class="grid gap-4 md:grid-cols-2">
    <label class="grid gap-y-2">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Conexão</span>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="conexao">
                @foreach ($this->conexoes as $nome => $rotulo)
                    <option value="{{ $nome }}">{{ $rotulo }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </label>

    @unless ($semUnidade ?? false)
        <label class="grid gap-y-2">
            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Unidade</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="unidade">
                    <option value="">Unidade padrão da conexão</option>
                    @foreach ($this->unidades as $id => $rotulo)
                        <option value="{{ $id }}">{{ $rotulo }} ({{ $id }})</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    @endunless
</div>
