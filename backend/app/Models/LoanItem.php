<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'barang_id',
        'qty_dipinjam',
        'qty_menunggu',
        'qty_kembali_baik',
        'qty_kembali_rusak',
        'qty_hilang',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}