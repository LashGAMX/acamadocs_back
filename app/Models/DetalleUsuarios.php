<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleUsuarios extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'detalle_usuarios';

    protected $primaryKey = 'Id_detalle';

    public $timestamps = true;

    protected $fillable = [
        'Id_usuario',
        'Id_departamento',
        'Nombre',
        'Firma',
        'Foto_perfil',
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
}
