<?php

namespace App\Http\Controllers;

use App\Models\WebContentHome;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class WebContentHomeController extends Controller
{
    public function index()
    {
        try {
            $webContent = WebContentHome::all();
            return ApiResponse::create('Succeeded', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $encodedData = json_encode($request->data);

            $validator = Validator::make(['data' => $encodedData], [
                'data' => 'required|json',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $decodedData = json_decode($encodedData, true);

            $webContent = WebContentHome::create([
                'id_user' => $user->id,
                'data' => $decodedData,
            ]);

            return ApiResponse::create('Contenido de la web creado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }


    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $encodedData = json_encode($request->data);

            $validator = Validator::make(['data' => $encodedData], [
                'data' => 'required|json',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $decodedData = json_decode($encodedData, true);

            $webContent = WebContentHome::findOrFail($id);

            $webContent->update([
                'id_user' => $user->id,
                'data' => $decodedData,
            ]);

            return ApiResponse::create('Contenido de la web actualizado correctamente', 200, $webContent);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el contenido de la web', 500, ['error' => $e->getMessage()]);
        }
    }
}

