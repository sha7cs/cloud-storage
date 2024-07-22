<?php

use App\Http\Controllers\DepartmentStorageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard.layouts.home');
});

Route::get('/dashboard', function () {
    return view('dashboard.layouts.home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::get('/upload-file' , [DepartmentStorageController::class , 'create'])->name('upload-file');
Route::post('/upload-file' , [DepartmentStorageController::class , 'store'])->name('upload-file');




require __DIR__.'/auth.php';
