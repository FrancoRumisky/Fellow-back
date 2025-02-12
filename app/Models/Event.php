<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // Especificar la tabla asociada
    public $table = "events";

    // Definir las relaciones

    // Relación con el organizador (usuario que creó el evento)
    public function organizer()
    {
        return $this->hasOne(Users::class, 'id', 'organizer_id');
    }

    // Relación con los asistentes
    public function attendees()
    {
        return $this->belongsToMany(User::class, 'event_attendees', 'event_id', 'user_id')
            ->withPivot('event_id', 'user_id')
            ->with('images'); // Agregar las imágenes de cada asistente
    }

    // Relación con reseñas o calificaciones
    public function reviews()
    {
        return $this->hasMany(EventReview::class, 'event_id', 'id');
    }
}
