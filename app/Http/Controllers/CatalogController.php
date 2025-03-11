<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Catalog;

class CatalogController extends Controller
{
    public function store(Request $request, $category)
    {
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

        return response()->json(['message' => 'Catálogo guardado con éxito', 'catalog' => $catalog], 201);
    }
}
