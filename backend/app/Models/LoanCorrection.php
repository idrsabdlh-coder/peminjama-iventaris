<?php

namespace App\Models;

class LoanCorrection extends ModelDasar
{
    protected $fillable = ['loan_id', 'kolom', 'nilai_lama', 'nilai_baru', 'alasan', 'user_id'];

    protected function casts(): array
    {
        return ['dibuat_pada' => 'datetime'];
    }

    public function loan() { return $this->belongsTo(Loan::class, 'loan_id'); }
    public function admin() { return $this->belongsTo(User::class, 'user_id'); }
}