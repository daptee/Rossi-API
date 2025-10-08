<?php
 
 use App\Http\Controllers\AttributeController;
 use App\Http\Controllers\CacheController;
 use App\Http\Controllers\CategoriesController;
 use App\Http\Controllers\ComponentController;
 use App\Http\Controllers\DistributorController;
 use App\Http\Controllers\ProductController;
 use App\Http\Controllers\ProvinceController;
 use App\Http\Controllers\SearchController;
 use App\Http\Controllers\WebContentAboutController;
 use App\Http\Controllers\WebContentHomeController;
 use Illuminate\Support\Facades\Route;
 use App\Http\Controllers\AuthController;
 use App\Http\Controllers\MaterialController;
 use App\Http\Controllers\BackupController;
 use App\Http\Controllers\CatalogController;
 use Illuminate\Http\Request;

Route::get('/install/dompdf', function (Request $request) {
    try {
        // Ejecuta composer desde PHP y devuelve la salida en pantalla
        $output = shell_exec('composer require barryvdh/laravel-dompdf 2>&1');

        if ($output === null) {
            return response()->json([
                'error' => 'No se pudo ejecutar composer. shell_exec puede estar deshabilitado o composer no está disponible.'
            ], 500);
        }

        return response("<pre>$output</pre>");
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/backup', [BackupController::class, 'createBackup'])->name('backup');

Route::get('/clear-cache', [CacheController::class, 'clearCache'])->name('clearCache');

 // Rutas de autenticación
Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('changePassword');
});

 // Rutas de categorías
Route::group([
    'middleware' => 'api',
    'prefix' => 'categories'
], function () {
    Route::get('/', [CategoriesController::class, 'index']);
    Route::post('/', [CategoriesController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [CategoriesController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [CategoriesController::class, 'destroy'])->middleware('admin');
});

// Rutas de materiales

Route::group([
    'middleware' => 'api',
    'prefix' => 'materials'
], function () {
    Route::get('/', [MaterialController::class, 'index']);
    Route::post('/', [MaterialController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [MaterialController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [MaterialController::class, 'delete'])->middleware('admin');
});

// Rutas de atributos

Route::group([
    'middleware' => 'api',
    'prefix' => 'attributes'
], function () {
    Route::get('/', [AttributeController::class, 'index']);
    Route::post('/', [AttributeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [AttributeController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [AttributeController::class, 'delete'])->middleware('admin');
});

// Rutas de productos

Route::group([
    'middleware' => 'api',
    'prefix' => 'product'
], function () {
    Route::get('/admin', [ProductController::class, 'indexAdmin'])->middleware('admin');
    Route::get('/', [ProductController::class, 'indexWeb']);
    Route::get('/{id}', [ProductController::class, 'indexProduct']);
    Route::get('/sku/{sku}', [ProductController::class, 'skuProduct']);
    Route::post('/', [ProductController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ProductController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('admin');
    Route::post('/pdf/product-customization', [ProductController::class, 'generarFichaProducto']);
});

// Rutas de distribuidores

Route::group([
    'middleware' => 'api',
    'prefix' => 'distributor'
], function () {
    Route::get('/', [DistributorController::class, 'index']);
    Route::post('/', [DistributorController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [DistributorController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [DistributorController::class, 'destroy'])->middleware('admin');
    Route::post('/send', [DistributorController::class, 'send']);
});

// Rutas del contenido de la web

Route::group([
    'middleware' => 'api',
    'prefix' => 'web-content-home'
], function () {
    Route::get('/', [WebContentHomeController::class, 'index']);
    Route::post('/', [WebContentHomeController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [WebContentHomeController::class, 'update'])->middleware('admin');
});

// Rutas del contenido sobre nosotros de la web

Route::group([
    'middleware' => 'api',
    'prefix' => 'web-content-about'
], function () {
    Route::get('/', [WebContentAboutController::class, 'index']);
    Route::post('/', [WebContentAboutController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [WebContentAboutController::class, 'update'])->middleware('admin');
});

// Rutas del componente

Route::group([
    'middleware' => 'api',
    'prefix' => 'component'
], function () {
    Route::get('/', [ComponentController::class, 'index']);
    Route::post('/', [ComponentController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ComponentController::class, 'update'])->middleware('admin');
});

// Rutas provinces

Route::group([
    'middleware' => 'api',
    'prefix' => 'provinces'
], function () {
    Route::get('/', [ProvinceController::class, 'index']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'search'
], function () {
    Route::get('/', [SearchController::class, 'index']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'catalog'
], function () {
    Route::get('/', [CatalogController::class, 'index']);
    Route::post('/{category}', [CatalogController::class, 'store'])->middleware('admin');
});
