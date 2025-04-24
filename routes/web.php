<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AddEnforcer;
use App\Http\Controllers\EnforcerAcc;
use App\Http\Controllers\ViolationController;
use App\Http\Controllers\EnforcerAuthController;
use App\Http\Controllers\EnforcerManagementController;
use App\Http\Controllers\ViolatorManagementController;
use App\Http\Controllers\ViolatorAuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AdminTicketController;

Route::get('/', function () {
    return view('welcome');
});
// Admin login routes
Route::get('/alogin', [AuthController::class, 'showLogin'])->name('admin.showLogin');
Route::post('/alogin', [AuthController::class, 'login'])->name('admin.login');
Route::post('/alogout', [AuthController::class, 'logout'])->name('admin.logout');

// Enforcer login routes
Route::get('/plogin', [EnforcerAuthController::class, 'showLogin'])->name('enforcer.showLogin');
Route::post('/plogin', [EnforcerAuthController::class,'login'])->name('enforcer.login');
Route::post('/plogout', [EnforcerAuthController::class,'logout'])->name('enforcer.logout');

// Violator login routes
Route::get('/vlogin', [ViolatorAuthController::class, 'showLogin'])->name('violator.showLogin');
Route::post('/vlogin', [ViolatorAuthController::class, 'login'])->name('violator.login');
Route::post('/vlogout', [ViolatorAuthController::class, 'logout'])->name('violator.logout');


// Enforcer protected routes
Route::middleware('enforcer')->group(function () {
    Route::resource('enforcerCreate', EnforcerAcc::class);
    Route::resource('enforcerTicket', TicketController::class);
    Route::resource('enf', EnforcerManagementController::class);
    Route::get('violators/suggestions', [TicketController::class, 'suggestions'])->name('enforcer.violators.suggestions');
    Route::get('violators/{id}', [TicketController::class, 'show'])->name('enforcer.violators.show');
});

// Admin protected routes
Route::middleware('admin')->group(function () {
    Route::get('/admin', [AdminManagementController::class,'adminDash'])->name('admin.dashboard');
    Route::resource('violation', ViolationController::class); 
    Route::resource('enforcer', AddEnforcer::class);
    Route::resource('ticket', AdminTicketController::class);
    Route::get('/enforcer/partial', [AddEnforcer::class, 'partial'])->name('enforcer.partial');
    Route::get('enforcer/{enforcer}/json', [AddEnforcer::class, 'json'])
     ->name('enforcer.json');

});

Route::middleware('violator')->group(function () {
    Route::get('/vdash', [ViolatorManagementController::class,'violatorDash'])->name('violator.dashboard');
});

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
