<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AddEnforcer;
use App\Http\Controllers\EnforcerAcc;
use App\Http\Controllers\ViolationController;
use App\Http\Controllers\EnforcerAuthController;
use App\Http\Controllers\EnforcerManagementController;
use App\Http\Controllers\TicketController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/alogin', [AuthController::class, 'showLogin'])->name('admin.showLogin');
Route::post('/alogin', [AuthController::class, 'login'])->name('admin.login');
Route::post('/alogout', [AuthController::class, 'logout'])->name('admin.logout');

// Enforcer login routes
Route::get('/plogin', [EnforcerAuthController::class, 'showLogin'])->name('enforcer.showLogin');
Route::post('/plogin', [EnforcerAuthController::class,'login'])->name('enforcer.login');
Route::post('/plogout', [EnforcerAuthController::class,'logout'])->name('enforcer.logout');

// Enforcer protected routes
Route::middleware('enforcer')->group(function () {
    Route::get('/pdash', [EnforcerManagementController::class,'enforcerDash'])->name('enforcer.dashboard');
    Route::resource('enforcerCreate', EnforcerAcc::class);
    Route::resource('enforcerTicket', TicketController::class);
    Route::resource('enf', EnforcerManagementController::class);
});

// Admin protected routes
Route::middleware('admin')->group(function () {
    Route::get('/admin', [AdminManagementController::class,'adminDash'])->name('admin.dashboard');
    Route::resource('violation', ViolationController::class); 
    Route::resource('enforcer', AddEnforcer::class);
});


//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
