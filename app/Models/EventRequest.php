<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRequest extends Model
{
    use HasFactory;

    // Especificar la tabla asociada
    protected $table = "event_requests";

    // Habilitar la asignación masiva
    protected $fillable = [
        'event_id',
        'user_id',
        'status'
    ];

    // 🔹 Relación con el evento
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    // 🔹 Relación con el usuario solicitante
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
