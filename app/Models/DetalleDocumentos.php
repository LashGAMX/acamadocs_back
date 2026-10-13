<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleDocumentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'detalle_documentos';

    protected $primaryKey = 'Id_detalle';

    public $timestamps = true;

    protected $fillable = [
        'Id_documento',
        'Contenido',
        'Url',
        'Numero_revision',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documentos::class, 'Id_documento', 'Id_documento');
    }
}
