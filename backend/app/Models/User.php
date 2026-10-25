<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    protected $fillable = ['name', 'email', 'password', 'aktif', 'login_terakhir'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'aktif' => 'boolean',
            'login_terakhir' => 'datetime',
            'dibuat_pada' => 'datetime',
        ];
    }

    // Tabel users tidak punya kolom remember_token.
    public function getRememberTokenName()
    {
        return '';
    }
}