<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedimientos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'procedimientos';

    protected $primaryKey = 'Id_procedimiento';

    public $timestamps = true;

    protected $fillable = [
        'Procedimiento',
        'Clave_procedimiento',
        'Id_user_c',
        'Id_user_m',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(Documentos::class, 'Id_procedimiento', 'Id_procedimiento');
    }
}
