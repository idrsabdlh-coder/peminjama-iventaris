<?php

namespace App\Models;

class Division extends ModelDasar
{
    protected $fillable = ['nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function loans() { return $this->hasMany(Loan::class, 'division_id'); }
}