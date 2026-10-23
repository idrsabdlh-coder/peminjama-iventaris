<?php

namespace App\Models;

class AuditLog extends ModelDasar
{
    protected $fillable = ['user_id', 'aksi', 'entitas', 'entitas_id', 'detail'];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function admin() { return $this->belongsTo(User::class, 'user_id'); }
}