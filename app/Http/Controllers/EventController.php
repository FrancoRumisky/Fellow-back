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
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{

    public function events()
    {
        return view('events');
    }

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
            'end_time_epoch' => 'required|numeric',
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
        $event->description = $request->description;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->start_time = $request->start_time;
        $event->end_time = $request->end_time;
        $event->end_time_epoch = $request->end_time_epoch;
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
        $nowEpoch = now()->timestamp; // Obtener la hora actual del servidor en Epoch

        // Obtener eventos activos
        $events = Event::where('status', 'active')->get();

        foreach ($events as $event) {

            // Comparar el epoch del evento con la hora actual
            if ($event->end_time_epoch <= $nowEpoch) {
                $event->status = 'completed';
                $event->save();
            } else {
                Log::info("❌ Evento ID: {$event->id} aún está activo.");
            }
        }
        return response()->json(['status' => true, 'message' => 'Eventos expirados actualizados']);
    }

    public function eventsList(Request $request)
    {
        $totalData = Event::count();
        $events = Event::orderBy('id', 'DESC')->get();

        $columns = [
            0 => 'id',
            1 => 'organizer_id',
            2 => 'start_date',
            3 => 'end_date',
            4 => 'available_slots',
            5 => 'capacity',
            6 => 'status',
        ];

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column', 0)] ?? 'id';
        $dir = $request->input('order.0.dir', 'desc');

        $totalFiltered = $totalData;

        if (empty($request->input('search.value'))) {
            $events = Event::orderBy($order, $dir)
                ->offset($start)
                ->limit($limit)
                ->get();
        } else {
            $search = $request->input('search.value');
            $events = Event::where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();

            $totalFiltered = Event::where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->count();
        }

        $data = [];
        foreach ($events as $event) {
            $organizer = User::where('id', $event->organizer_id)->first();
            $organizerName = $organizer ? $organizer->full_name : 'Unknown';

            $eventImage = $event->image ? asset('public/storage/' . $event->image) : asset('default-image.jpg');

            $viewEvent = '<button type="button" class="btn btn-primary viewEvent commonViewBtn" 
                        data-bs-toggle="modal" 
                        data-title="' . htmlspecialchars($event->title, ENT_QUOTES) . '" 
                        data-organizer="' . htmlspecialchars($organizerName, ENT_QUOTES) . '" 
                        data-start-date="' . $event->start_date . '" 
                        data-end-date="' . $event->end_date . '" 
                        data-available-slots="' . $event->available_slots . '"
                        data-capacity="' . $event->capacity . '"
                        data-status="' . $event->status . '"
                        data-description="' . htmlspecialchars($event->description, ENT_QUOTES) . '" 
                        data-image="' . $eventImage . '" 
                        rel="' . $event->id . '">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle></svg> View Event
                     </button>';

            $data[] = [
                $viewEvent,
                $organizerName,
                $event->start_date,
                $event->end_date,
                $event->available_slots,
                $event->capacity,
                $event->status,
                '<button class="btn btn-danger deleteEvent" rel="' . $event->id . '">Delete</button>', // 7 (Acciones)
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }
}
