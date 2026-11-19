<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['permissoes' => 'array'];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
