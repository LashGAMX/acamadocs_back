<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentosUsuarios extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos_usuarios';

    protected $primaryKey = 'Id_documento_usuario';

    public $timestamps = true;

    protected $fillable = [
        'Id_documento',
        'Id_usuario',
        'Estado_revision',
        'Estado_lectura',
        'Porcentaje_lectura',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documentos::class, 'Id_documento', 'Id_documento');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'Id_usuario', 'Id_user');
    }
}
