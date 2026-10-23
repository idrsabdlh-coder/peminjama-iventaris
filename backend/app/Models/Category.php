<?php

namespace App\Models;

class Category extends ModelDasar
{
    protected $fillable = ['nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function items() { return $this->hasMany(Item::class, 'kategori_id'); }
}