<?php
 
 use Illuminate\Support\Facades\Route;
 use App\Http\Controllers\AuthController;
 use App\Http\Controllers\ProductCategoryController;
 
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