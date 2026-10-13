<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentosDepartamentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos_departamentos';

    protected $primaryKey = 'Id_documento_departamento';

    public $timestamps = true;

    protected $fillable = [
        'Id_documento',
        'Id_departamento',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documentos::class, 'Id_documento', 'Id_documento');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamentos::class, 'Id_departamento', 'Id_departamento');
    }
}
