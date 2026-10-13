<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departamentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'departamentos';

    protected $primaryKey = 'Id_departamento';

    public $timestamps = true;

    protected $fillable = [
        'Departamento',
        'Descripcion',
        'Id_user_c',
        'Id_user_m',
    ];

    public function detallesUsuarios(): HasMany
    {
        return $this->hasMany(DetalleUsuarios::class, 'Id_departamento', 'Id_departamento');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentosDepartamentos::class, 'Id_departamento', 'Id_departamento');
    }
}
