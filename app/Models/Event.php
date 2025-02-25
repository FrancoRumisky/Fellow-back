<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // Especificar la tabla asociada
    public $table = "events";

    // Habilitar la asignación masiva para estos campos
    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'latitude',
        'longitude',
        'capacity',
        'is_public',
        'organizer_id',
        'interests'
    ];

    // Definir las relaciones

    // Relación con el organizador (usuario que creó el evento)
    public function organizer()
    {
        return $this->hasOne(Users::class, 'id', 'organizer_id');
    }

    // Relación con los asistentes
    public function attendees()
    {
        return $this->belongsToMany(Users::class, 'event_attendees', 'event_id', 'user_id');
    }

    // Relación con reseñas o calificaciones
    public function reviews()
    {
        return $this->hasMany(EventReview::class, 'event_id', 'id');
    }
}
