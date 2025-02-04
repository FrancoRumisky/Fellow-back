<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Interest;
use App\Models\Report;
use App\Models\FollowingList;

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
            'user_id' => 'required',
            'user_lat' => 'required|numeric',
            'user_lng' => 'required|numeric',
            'interest' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:10',
            'offset' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $userLat = $request->user_lat;
        $userLng = $request->user_lng;
        $interest = $request->interest;
        $limit = $request->input('limit', 10);
        $offset = $request->input(
            'offset',
            0
        );
        $currentUserId = $request->user_id; // Obtener ID del usuario actual

        // Obtener eventos con organizador
        $query = Event::query()->with(['organizer.images']);

        // Filtrar por interés si está presente
        if (!empty($interest)) {
            $query->where('interests', 'LIKE', "%$interest%");
        }

        // Ordenar por fecha
        $query->orderBy('start_date', 'asc');
        $query->skip($offset)->take($limit);

        // Calcular proximidad y agregar asistentes
        $events = $query->get()->map(function ($event) use ($userLat, $userLng, $currentUserId) {
            $distance = $this->calculateDistance($userLat, $userLng, $event->latitude, $event->longitude);
            $event->distance = $distance;

            // Obtener nombres de intereses
            $interestIds = explode(',', $event->interests);
            $interestNames = Interest::whereIn('id', $interestIds)->pluck('title')->toArray();
            $event->interest = $interestNames;

            // Obtener asistentes del evento
            $attendeeIds = json_decode($event->attendees) ?? [];
            $attendees = User::whereIn('id', $attendeeIds)->get()->map(function ($user) use ($currentUserId) {
                $isFollowed = FollowingList::where('my_user_id', $currentUserId)
                    ->where('user_id', $user->id)
                    ->exists();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_image' => $user->profile_image,
                    'is_followed' => $isFollowed,
                ];
            });

            $event->attendees = $attendees;

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
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'location' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'capacity' => 'required|integer|min:1',
            'is_public' => 'required|boolean',
            'organizer_id' => 'required|integer|exists:users,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Imagen opcional
            'interests' => 'nullable|string', // IDs de intereses separados por comas
        ]);

        // Manejar errores de validación
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        // Manejar la imagen si se proporciona
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('events', 'public'); // Guardar imagen en almacenamiento público
        }

        // Crear el evento
        $event = new Event();
        $event->title = $request->title;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->start_time = $request->start_time;
        $event->end_time = $request->end_time;
        $event->location = $request->location;
        $event->latitude = $request->latitude;
        $event->longitude = $request->longitude;
        $event->capacity = $request->capacity;
        $event->is_public = $request->is_public;
        $event->organizer_id = $request->organizer_id;
        $event->available_slots = $request->capacity; // Inicialmente los espacios disponibles son iguales a la capacidad
        $event->image = $imagePath; // Asignar la ruta de la imagen si existe
        $event->interests = $request->interests; // Guardar los intereses relacionados
        $event->attendees = [];

        // Guardar el evento en la base de datos
        $event->save();

        return response()->json(['status' => true, 'message' => 'Evento creado exitosamente', 'data' => $event]);
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

    //Reportar un evento
    public function reportEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required',
            'reason' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $event = Event::where('id', $request->event_id)->first();

        if ($event != null) {
            $reportType = 2; // Nuevo tipo para eventos

            $report = new Report();
            $report->type = $reportType; // Indica que es un reporte de evento
            $report->event_id = $request->event_id; // Relaciona con el evento
            $report->reason = $request->reason;
            $report->description = $request->description;
            $report->save();

            return response()->json([
                'status' => true,
                'message' => 'Event Reported Successfully',
                'data' => $report,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Event Not Found',
            ]);
        }
    }
}
