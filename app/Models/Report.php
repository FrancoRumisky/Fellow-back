<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    public $table= "reports";
    public $timestamps= false;

    public function user()
    {
        return $this->hasOne(Users::class, 'id', 'user_id');
    }

    public function event()
    {
        return $this->hasOne(Event::class, 'id', 'event_id'); // Relación con eventos
    }
}
