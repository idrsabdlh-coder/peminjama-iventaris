<?php

namespace App\Models;

class Setting extends ModelDasar
{
    public $timestamps = true;

    protected $fillable = ['kunci', 'nilai'];
}