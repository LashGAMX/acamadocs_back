<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalDocumentos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'personal_documentos';

    protected $primaryKey = 'Id_personal';

    public $timestamps = true;

    protected $fillable = [
        'Id_documento',
        'Id_elabora',
        'Id_autoriza',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documentos::class, 'Id_documento', 'Id_documento');
    }

    public function elabora(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'Id_elabora', 'Id_user');
    }

    public function autoriza(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'Id_autoriza', 'Id_user');
    }
}
