<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenusUsuarios extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'menus_usuarios';

    protected $primaryKey = 'Id_menu';

    public $timestamps = true;

    protected $fillable = [
        'Id_usuario',
        'Id_departamento',
        'Id_procedimiento',
        'Id_user_c',
        'Id_user_m',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'Id_usuario', 'Id_user');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamentos::class, 'Id_departamento', 'Id_departamento');
    }

    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(Procedimientos::class, 'Id_procedimiento', 'Id_procedimiento');
    }
}
