<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Catalog;
use Exception;

class CatalogController extends Controller
{
    public function index()
    {
        try {
            $catalogs = Catalog::all();

            return ApiResponse::create('Catalogos obtenidos correctamente', 200, $catalogs);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los catalogos', 500, ['error' => $e->getMessage()]);
        }
    }
    
    public function store(Request $request, $category)
    {
        try {
            // Mapeo de nombres en la URL a los nombres correctos
            $categoryMap = [
                'diseno-y-oficina' => 'Diseño y Oficina',
                'plasticos' => 'Plasticos',
                'telas' => 'Telas'
            ];

            // Verificar si la categoría existe en el mapa
            if (!array_key_exists($category, $categoryMap)) {
                return response()->json(['error' => 'Categoría no válida'], 400);
            }

            $categoryName = $categoryMap[$category]; // Obtener el nombre correcto

            // Validar el archivo PDF
            $request->validate([
                'pdf' => 'required|mimes:pdf|max:2048' // Máx 2MB
            ]);

            // Guardar el archivo en 'public/storage/catalog'
            $file = $request->file('pdf');
            $fileName = str_replace(' ', '_', strtolower($categoryName)) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'storage/catalog/' . $fileName;
            $file->move(public_path('storage/catalog'), $fileName);

            // Guardar en la base de datos
            $catalog = Catalog::updateOrCreate(
                ['name' => $categoryName], // Guardar con el nombre correcto
                ['pdf' => $filePath]
            );

            return ApiResponse::create('Catalogo creado correctamente', 201, $catalog);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un catalogo', 500, ['error' => $e->getMessage()]);
        }
    }
}
