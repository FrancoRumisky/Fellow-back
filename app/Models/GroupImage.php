<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupImage extends Model
{
    use HasFactory;

    protected $table = "group_images"; // Especificamos la tabla en la base de datos

    protected $fillable = [
        'group_id',
        'image',
    ];

    public $timestamps = false; // Si no necesitas `created_at` y `updated_at`
}
