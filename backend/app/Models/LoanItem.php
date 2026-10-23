<?php

namespace App\Models;

class LoanItem extends ModelDasar
{
    protected $fillable = ['loan_id', 'item_id', 'qty_dipinjam'];

    public function loan() { return $this->belongsTo(Loan::class, 'loan_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id')->withTrashed(); }
    public function barisKlaim() { return $this->hasMany(ReturnClaimLine::class, 'loan_item_id'); }
}