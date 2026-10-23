<?php

namespace App\Models;

class ReturnClaim extends ModelDasar
{
    protected $fillable = [
        'kode_klaim', 'loan_id', 'catatan_peminjam', 'hari_terlambat', 'status',
        'diverifikasi_oleh', 'diverifikasi_pada', 'catatan_admin', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'diklaim_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function loan() { return $this->belongsTo(Loan::class, 'loan_id'); }
    public function baris() { return $this->hasMany(ReturnClaimLine::class, 'claim_id'); }
    public function verifikator() { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
}