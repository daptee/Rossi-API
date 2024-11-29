<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Models\Distributor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProvinceController extends Controller
{
    // GET ALL (para admin)
    public function index()
    {
        try {
            $attributes = Province::with('localities')->get();

            return ApiResponse::create('Succeeded', 200, $attributes);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer todos los atributos', 500, ['error' => $e->getMessage()]);
        }
    }

}
