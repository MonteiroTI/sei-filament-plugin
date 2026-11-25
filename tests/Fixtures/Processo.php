<?php

namespace MonteiroTI\SeiFilament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\Concerns\HasSeiProcesso;

class Processo extends Model
{
    use HasSeiProcesso;

    protected $table = 'processos_teste';

    protected $guarded = [];
}
