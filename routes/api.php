<?php
 
 use App\Http\Controllers\AttributeController;
 use App\Http\Controllers\ComponentController;
 use App\Http\Controllers\DistributorController;
 use App\Http\Controllers\ProductController;
 use App\Http\Controllers\WebContentAboutController;
 use App\Http\Controllers\WebContentHomeController;
 use Illuminate\Support\Facades\Route;
 use App\Http\Controllers\AuthController;
 use App\Http\Controllers\ProductsCategoriesController;
 use App\Http\Controllers\MaterialController;
 
 // Rutas de autenticación
Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

 // Rutas de categorías
Route::group([
    'middleware' => 'api',
    'prefix' => 'categories'
], function () {
    Route::get('/', [ProductsCategoriesController::class, 'index']);
    Route::post('/', [ProductsCategoriesController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ProductsCategoriesController::class, 'update'])->middleware('admin');
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
    Route::post('/', [ProductController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ProductController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('admin');
});

// Rutas de distribuidores

Route::group([
    'middleware' => 'api',
    'prefix' => 'distributor'
], function () {
    Route::get('/', [DistributorController::class, 'index']);
    Route::post('/', [DistributorController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [DistributorController::class, 'update'])->middleware('admin');
});

// Rutas del contenido de la web

Route::group([
    'middleware' => 'api',
    'prefix' => 'web-content-home'
], function () {
    Route::get('/', [WebContentHomeController::class, 'index']);
    Route::post('/', [WebContentHomeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [WebContentHomeController::class, 'update'])->middleware('admin');
});

// Rutas del contenido sobre nosotros de la web

Route::group([
    'middleware' => 'api',
    'prefix' => 'web-content-about'
], function () {
    Route::get('/', [WebContentAboutController::class, 'index']);
    Route::post('/', [WebContentAboutController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [WebContentAboutController::class, 'update'])->middleware('admin');
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