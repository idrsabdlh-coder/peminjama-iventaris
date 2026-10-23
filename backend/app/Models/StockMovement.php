<?php

namespace App\Models;

class StockMovement extends ModelDasar
{
    protected $fillable = [
        'item_id', 'jenis', 'delta_total', 'delta_tersedia', 'delta_rusak',
        'ref_tipe', 'ref_id', 'user_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'delta_total' => 'integer',
            'delta_tersedia' => 'integer',
            'delta_rusak' => 'integer',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function item() { return $this->belongsTo(Item::class, 'item_id')->withTrashed(); }
    public function admin() { return $this->belongsTo(User::class, 'user_id'); }
}