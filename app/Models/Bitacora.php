<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    public $timestamps = false;

    protected $fillable = ['user_id', 'usuario', 'accion', 'objetivo', 'resultado', 'detalle', 'ip'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
