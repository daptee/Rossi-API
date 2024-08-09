<?php
 
 use App\Http\Controllers\AttributeController;
 use App\Http\Controllers\ProductController;
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
    Route::put('/{id}', [ProductsCategoriesController::class, 'update'])->middleware('admin');
});

// Rutas de materiales

Route::group([
    'middleware' => 'api',
    'prefix' => 'materials'
], function () {
    Route::get('/', [MaterialController::class, 'index']);
    Route::post('/', [MaterialController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [MaterialController::class, 'update'])->middleware('admin');
});

// Rutas de atributos

Route::group([
    'middleware' => 'api',
    'prefix' => 'attributes'
], function () {
    Route::get('/', [AttributeController::class, 'index']);
    Route::post('/', [AttributeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [AttributeController::class, 'update'])->middleware('admin');
});

// Rutas de atributos

Route::group([
    'middleware' => 'api',
    'prefix' => 'product'
], function () {
    Route::get('/admin', [ProductController::class, 'indexAdmin'])->middleware('admin');
    Route::get('/', [ProductController::class, 'indexWeb']);
    Route::get('/{id}', [ProductController::class, 'indexProduct'])->middleware('admin');
    Route::post('/', [ProductController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ProductController::class, 'update'])->middleware('admin');
});