<?php

namespace App\Models;

class Setting extends ModelDasar
{
    protected $primaryKey = 'kunci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['kunci', 'nilai'];

    protected function casts(): array
    {
        return ['diubah_pada' => 'datetime'];
    }
}