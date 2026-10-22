<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_pinjam',
        'nama_peminjam',
        'divisi_id',
        'tipe_peminjam',
        'telepon',
        'tanggal_pinjam',
        'batas_kembali',
        'jumlah_perpanjangan',
        'status',
    ];

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function items()
    {
        return $this->hasMany(LoanItem::class, 'loan_id');
    }
}