<?php

namespace App\Models;

class Loan extends ModelDasar
{
    protected $fillable = [
        'kode_pinjam', 'nama_peminjam', 'division_id', 'tipe_peminjam', 'telepon',
        'tanggal_pinjam', 'batas_kembali_awal', 'batas_kembali', 'idempotency_key',
        'dibatalkan_pada', 'alasan_batal', 'dibatalkan_oleh',
    ];

    // Telepon tidak pernah tampil di kiosk (BR-18). Admin memakai makeVisible().
    protected $hidden = ['telepon'];

    protected function casts(): array
    {
        return [
            'tanggal_pinjam' => 'date:Y-m-d',
            'batas_kembali_awal' => 'date:Y-m-d',
            'batas_kembali' => 'date:Y-m-d',
            'dibuat_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function division() { return $this->belongsTo(Division::class, 'division_id'); }
    public function items() { return $this->hasMany(LoanItem::class, 'loan_id'); }
    public function klaim() { return $this->hasMany(ReturnClaim::class, 'loan_id'); }
    public function perpanjangan() { return $this->hasMany(LoanExtension::class, 'loan_id'); }
    public function koreksi() { return $this->hasMany(LoanCorrection::class, 'loan_id'); }
    public function dibatalkanOleh() { return $this->belongsTo(User::class, 'dibatalkan_oleh'); }
}