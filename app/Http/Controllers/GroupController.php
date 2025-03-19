<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlobalFunction;
use App\Models\GroupImage;

class GroupController extends Controller
{

    function updateGroupImage(Request $req)
    {
        // Validamos que el grupo tenga un ID válido (ya que viene de Firebase)
        if (!$req->has('group_id')) {
            return response()->json(['status' => false, 'message' => 'ID de grupo no proporcionado']);
        }

        // Verificamos si el usuario ha subido una imagen
        if (!$req->hasFile('image')) {
            return response()->json(['status' => false, 'message' => 'No se recibió ninguna imagen']);
        }

        // Guardamos la imagen en el almacenamiento público
        $imagePath = $req->file('image')->store('group_images', 'public');

        // Guardamos la URL en la base de datos
        GroupImage::create([
            'group_id' => $req->group_id, // ID del grupo en Firebase
            'image' => $imagePath, // Ruta de la imagen guardada
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Imagen actualizada con éxito',
            'imageUrl' => asset("storage/{$imagePath}") // Devolvemos la URL completa
        ]);
    }

    public function deleteGroupImage(Request $request)
    {
        // Validar que se proporciona un group_id
        if (!$request->has('group_id')) {
            return response()->json(['status' => false, 'message' => 'ID de grupo no proporcionado'], 400);
        }

        $groupId = $request->group_id;

        // Buscar la imagen asociada al grupo
        $image = GroupImage::where('group_id', $groupId)->first();

        if (!$image) {
            return response()->json(['status' => false, 'message' => 'No se encontró imagen para este grupo'], 404);
        }

        // Verificar si el archivo existe antes de eliminarlo
        $imagePath = storage_path('app/public/' . $image->image);
        if (file_exists($imagePath)) {
            unlink($imagePath); // Eliminar la imagen física
        }

        // Eliminar el registro de la base de datos
        $image->delete();

        return response()->json(['status' => true, 'message' => 'Imagen eliminada correctamente']);
    }
   
}
