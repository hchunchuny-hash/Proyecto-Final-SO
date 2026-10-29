<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Simulacion extends Model
{
    protected $table = 'simulaciones';

    protected $fillable = [
        'user_id', 'algoritmo', 'quantum', 'procesos',
        'espera_promedio', 'retorno_promedio', 'cambios_contexto', 'resultado',
    ];

    protected function casts(): array
    {
        return [
            'procesos' => 'array',
            'resultado' => 'array',
            'espera_promedio' => 'float',
            'retorno_promedio' => 'float',
        ];
    }
}
