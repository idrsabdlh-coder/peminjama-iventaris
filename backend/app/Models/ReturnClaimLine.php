<?php

namespace App\Models;

class ReturnClaimLine extends ModelDasar
{
    protected $fillable = [
        'claim_id', 'loan_item_id', 'kondisi_dilaporkan', 'qty',
        'qty_baik', 'qty_rusak', 'qty_hilang', 'qty_ditolak',
    ];

    public function klaim() { return $this->belongsTo(ReturnClaim::class, 'claim_id'); }
    public function loanItem() { return $this->belongsTo(LoanItem::class, 'loan_item_id'); }
}