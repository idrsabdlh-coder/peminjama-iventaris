<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends ModelDasar
{
    use SoftDeletes;

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'nama', 'tipe', 'deskripsi', 'kategori_id',
        'total', 'tersedia', 'kondisi', 'boleh_diperpanjang',
    ];

    // Laravel casts property for attribute type casting
    protected $casts = [
        'total' => 'integer',
        'tersedia' => 'integer',
        'tipe' => 'string',
        'boleh_diperpanjang' => 'boolean',
        'dibuat_pada' => 'datetime',
        'diubah_pada' => 'datetime',
        'dihapus_pada' => 'datetime',
    ];

    public function kategori()
    {
        return $this->belongsTo(Category::class, 'kategori_id');
    }

    public function loanItems()
    {
        return $this->hasMany(LoanItem::class, 'item_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
