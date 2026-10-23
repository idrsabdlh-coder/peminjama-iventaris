<?php

namespace App\Models;

class LoanExtension extends ModelDasar
{
    protected $fillable = [
        'loan_id', 'batas_lama', 'batas_baru', 'tambahan_hari',
        'alasan', 'diajukan_oleh', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'batas_lama' => 'date:Y-m-d',
            'batas_baru' => 'date:Y-m-d',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function loan() { return $this->belongsTo(Loan::class, 'loan_id'); }
    public function admin() { return $this->belongsTo(User::class, 'user_id'); }
}