<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use File;
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
            ini_set('upload_max_filesize', '50M');
            ini_set('post_max_size', '50M');
            // Mapeo de nombres en a URL a los nombres correctos
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
                'pdf' => 'required|mimes:pdf|max:51200' // Máx 50MB
            ]);

            // Buscar si ya existe un catálogo con ese nombre
            $existingCatalog = Catalog::where('name', $categoryName)->first();

            if ($existingCatalog) {
                // Eliminar el PDF anterior si existe
                $oldPdfPath = public_path($existingCatalog->pdf);
                if (File::exists($oldPdfPath)) {
                    File::delete($oldPdfPath);
                }

                // Eliminar el catálogo existente
                $existingCatalog->delete();
            }

            // Guardar el archivo en 'public/storage/catalog'
            $file = $request->file('pdf');
            $fileName = str_replace(' ', '_', strtolower($categoryName)) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'storage/catalog/' . $fileName;
            $file->move(public_path('storage/catalog'), $fileName);

            // Crear el nuevo catálogo en la base de datos
            $catalog = Catalog::create([
                'name' => $categoryName,
                'pdf' => $filePath
            ]);

            return ApiResponse::create('Catalogo creado correctamente', 201, $catalog);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un catalogo', 500, ['error' => $e->getMessage()]);
        }
    }
}
