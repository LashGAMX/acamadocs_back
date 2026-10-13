<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuarios extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $primaryKey = 'Id_user';

    public $timestamps = true;

    protected $fillable = [
        'User',
        'Pass',
        'Rol',
        'Id_user_c',
        'Id_user_m',
    ];

    protected $hidden = [
        'Pass',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->Pass;
    }

    protected function casts(): array
    {
        return [
            'Pass' => 'hashed',
        ];
    }

    public function detalle(): HasOne
    {
        return $this->hasOne(DetalleUsuarios::class, 'Id_usuario', 'Id_user');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentosUsuarios::class, 'Id_usuario', 'Id_user');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(MenusUsuarios::class, 'Id_usuario', 'Id_user');
    }
}
