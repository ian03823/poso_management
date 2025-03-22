<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosoManagementController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('pmc', PosoManagementController::class);    


Route::get('/login', [AuthController::class, 'showLogin'])->name('show.login');
Route::post('/login', [AuthController::class,'login'])->name('login');


route::get('/admin', [PosoManagementController::class,'index'])->name('admin.index');
route::get('/admin/create', [PosoManagementController::class,'create'])->name('admin.create');
route::get('/admin/show', [PosoManagementController::class,'show'])->name('admin.show');

// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
