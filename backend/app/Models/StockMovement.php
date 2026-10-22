<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'barang_id',
        'loan_id',
        'tipe_aksi',
        'perubahan_tersedia',
        'perubahan_dipinjam',
        'perubahan_menunggu',
        'perubahan_rusak',
        'perubahan_total',
        'keterangan',
    ];
}