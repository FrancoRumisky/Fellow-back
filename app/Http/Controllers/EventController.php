<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Interest;
use App\Models\Report;
use App\Models\FollowingList;
use App\Models\Images;
use Carbon\Carbon;

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
        $offset = $request->input('offset', 0);
        $currentUserId = $request->user_id;

        // Obtener eventos con organizador y asistentes
        $query = Event::query()->with(['organizer.images', 'attendees.images'])->where('status', '!=', 'completed');

        // Filtrar por interés si está presente
        if (!empty($interest)) {
            $query->where('interests', 'LIKE', "%$interest%");
        }

        // Ordenar por fecha
        $query->orderBy('start_date', 'asc');
        $query->skip($offset)->take($limit);

        // Procesar los eventos
        $events = $query->get()->map(function ($event) use ($userLat, $userLng, $currentUserId) {
            $distance = $this->calculateDistance($userLat, $userLng, $event->latitude, $event->longitude);
            $event->distance = $distance;

            // Obtener nombres de intereses
            $interestIds = explode(',', $event->interests);
            $interestNames = Interest::whereIn('id', $interestIds)->pluck('title')->toArray();
            $event->interest = $interestNames;

            // Obtener asistentes con imágenes
            $event->attendees = $event->attendees->map(function ($user) use ($currentUserId) {
                $isFollowed = FollowingList::where('my_user_id', $currentUserId)
                    ->where('user_id', $user->id)
                    ->exists();

                // Obtener la imagen del usuario desde la tabla `images`
                $profileImage = Images::where('user_id', $user->id)->value('image');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_image' => $profileImage ?? '',  // Si no hay imagen, devuelve una cadena vacía
                    'is_followed' => $isFollowed,
                ];
            });
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
            'description' => 'nullable|string',
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

        $startDateTime = Carbon::parse("{$request->start_date} {$request->start_time}")->setTimezone('UTC');
        $endDateTime = Carbon::parse("{$request->end_date} {$request->end_time}")->setTimezone('UTC');
        
        // Crear el evento
        $event = new Event();
        $event->title = $request->title;
        $event->description = $request->description;
        $event->start_date = $startDateTime->toDateString();
        $event->end_date = $endDateTime->toDateString();
        $event->start_time = $startDateTime->toTimeString();
        $event->end_time = $endDateTime->toTimeString();
        $event->location = $request->location;
        $event->latitude = $request->latitude;
        $event->longitude = $request->longitude;
        $event->capacity = $request->capacity;
        $event->is_public = $request->is_public;
        $event->organizer_id = $request->organizer_id;
        $event->available_slots = $request->capacity; // Inicialmente los espacios disponibles son iguales a la capacidad
        $event->image = $imagePath; // Asignar la ruta de la imagen si existe
        $event->interests = $request->interests; // Guardar los intereses relacionados


        // Guardar el evento en la base de datos
        $event->save();

        // Asignar al organizador como asistente en la tabla intermedia después de guardar
        $event->attendees()->attach($request->organizer_id);
        $event->available_slots -= 1; // Reducir el número de espacios disponibles

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

    public function joinEvent(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Manejar errores de validación
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        // Obtener el evento
        $event = Event::find($request->event_id);

        // Verificar si el evento tiene capacidad disponible
        if ($event->available_slots <= 0) {
            return response()->json(['status' => false, 'message' => 'No hay espacios disponibles en este evento.']);
        }

        // Verificar si el usuario ya está registrado como asistente en la tabla intermedia
        $alreadyJoined = $event->attendees()->where('user_id', $request->user_id)->exists();

        if ($alreadyJoined) {
            return response()->json(['status' => false, 'message' => 'Ya estás registrado en este evento.']);
        }

        // Registrar al usuario en el evento en la tabla intermedia
        $event->attendees()->attach($request->user_id);
        // Actualizar los datos del evento

        $event->available_slots -= 1; // Reducir el número de espacios disponibles
        $event->save();

        return response()->json(['status' => true, 'message' => 'Te has unido al evento con éxito.']);
    }

    public function getEventAttendees(Request $request)
    {
        // Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        // Obtener los parámetros
        $eventId = $request->input('event_id');

        // Buscar el evento y cargar asistentes con imágenes
        $event = Event::with('attendees.images')->find($eventId);

        if (!$event) {
            return response()->json(['status' => false, 'message' => 'Evento no encontrado']);
        }

        // Retornar los asistentes con la estructura completa sin modificar
        return response()->json(['status' => true, 'data' => $event->attendees]);
    }

    public function leaveEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $event = Event::find($request->event_id);
        $userId = $request->user_id;

        // Verificar si el usuario está registrado como asistente del evento
        $isAttendee = $event->attendees()->where('user_id', $userId)->exists();

        if (!$isAttendee) {
            return response()->json([
                'status' => false,
                'message' => 'El usuario no está registrado como asistente de este evento',
            ]);
        }

        // Eliminar al usuario de los asistentes del evento
        $event->attendees()->detach($userId);

        // Actualizar espacios disponibles
        $event->available_slots += 1;
        $event->save();

        return response()->json(['status' => true, 'message' => 'Has salido del evento con éxito']);
    }

    public function markExpiredEvents()
    {
        $now = now(); // Hora actual del servidor

        // Obtener eventos activos
        $events = Event::where('status', 'active')->get();

        foreach ($events as $event) {
            // Convertir la fecha y hora del evento a la zona horaria del servidor
            $eventEndDateTime = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$event->end_date} {$event->end_time}",
                'UTC'
            )->setTimezone(config('app.timezone')); // Convertir a la zona horaria del servidor

            // Si la fecha y hora convertida ya pasó, marcar el evento como expirado
            if ($eventEndDateTime->lessThanOrEqualTo($now)) {
                $event->status = 'completed';
                $event->save();
            }
        }

        return response()->json(['status' => true, 'message' => 'Eventos expirados actualizados']);
    }
}
