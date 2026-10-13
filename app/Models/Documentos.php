<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos';

    protected $primaryKey = 'Id_documento';

    public $timestamps = true;

    protected $fillable = [
        'Id_procedimiento',
        'Id_tipo',
        'Documento',
        'Descripcion',
        'Clave_documento',
        'Valido_desde',
        'Ultima_modificacion',
        'Id_user_c',
        'Id_user_m',
    ];

    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(Procedimientos::class, 'Id_procedimiento', 'Id_procedimiento');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumentos::class, 'Id_tipo', 'Id_tipo');
    }

    public function detalle(): HasOne
    {
        return $this->hasOne(DetalleDocumentos::class, 'Id_documento', 'Id_documento');
    }

    public function personal(): HasOne
    {
        return $this->hasOne(PersonalDocumentos::class, 'Id_documento', 'Id_documento');
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(DocumentosDepartamentos::class, 'Id_documento', 'Id_documento');
    }

    public function asignacionesUsuarios(): HasMany
    {
        return $this->hasMany(DocumentosUsuarios::class, 'Id_documento', 'Id_documento');
    }
}
