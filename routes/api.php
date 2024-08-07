<?php
 
 use App\Http\Controllers\AttributeController;
 use Illuminate\Support\Facades\Route;
 use App\Http\Controllers\AuthController;
 use App\Http\Controllers\ProductCategoryController;
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
    Route::get('/', [ProductCategoryController::class, 'index']);
    Route::post('/', [ProductCategoryController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ProductCategoryController::class, 'update'])->middleware('admin');
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