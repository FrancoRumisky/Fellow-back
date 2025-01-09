<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class EventController extends Controller
{

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id', 'id'); // Ajusta el modelo y campos según tu base de datos
    }


    // Obtener lista de eventos
    public function getEvents(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_lat' => 'required|numeric',
            'user_lng' => 'required|numeric',
            'interest' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $userLat = $request->user_lat;
        $userLng = $request->user_lng;
        $interest = $request->interest;

        // Obtener eventos con datos del organizador
        $query = Event::with('organizer');

        // Filtrar por interés
        if (!empty($interest)) {
            $query->where('interests', 'LIKE', "%$interest%");
        }

        // Ordenar por fecha (más recientes primero)
        $query->orderBy('start_date', 'asc');

        // Calcular proximidad
        $events = $query->get()->map(function ($event) use ($userLat, $userLng) {
            $distance = $this->calculateDistance($userLat, $userLng, $event->latitude, $event->longitude);
            $event->distance = $distance;
            return $event;
        })->sortBy('distance')->values();

        return response()->json(['status' => true, 'data' => $events]);
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // Radio de la tierra en kilómetros
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // Crear un evento
    public function createEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'location' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'capacity' => 'required|integer',
            'is_public' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $event = new Event();
        $event->fill($request->all());
        $event->save();

        return response()->json(['status' => true, 'message' => 'Event created successfully', 'data' => $event]);
    }

    // Actualizar un evento
    public function updateEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'title' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'location' => 'sometimes|string',
            'capacity' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $event = Event::find($request->event_id);
        $event->fill($request->all());
        $event->save();

        return response()->json(['status' => true, 'message' => 'Event updated successfully', 'data' => $event]);
    }

    // Eliminar un evento
    public function deleteEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        Event::destroy($request->event_id);

        return response()->json(['status' => true, 'message' => 'Event deleted successfully']);
    }

    // Obtener detalles de un evento
    public function getEventDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $event = Event::find($request->event_id);

        return response()->json(['status' => true, 'data' => $event]);
    }
}
