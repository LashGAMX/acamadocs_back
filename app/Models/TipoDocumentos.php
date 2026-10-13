<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoDocumentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipo_documentos';

    protected $primaryKey = 'Id_tipo';

    public $timestamps = true;

    protected $fillable = [
        'Tipo',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(Documentos::class, 'Id_tipo', 'Id_tipo');
    }
}
