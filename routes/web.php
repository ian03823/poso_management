<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
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
use App\Http\Controllers\ViolatorTableController;


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
    Route::get('/admin', [AdminDashboardController::class,'adminDash'])->name('admin.dashboard');

    //Violation routes
    Route::get('/violation/partial', [ViolationController::class,'partial'])->name('violation.partial');
    Route::resource('violation', ViolationController::class); 
    Route::get('violation/{violation}/json', [ViolationController::class, 'json'])
     ->name('violation.json');


    //Enforcer routes
    Route::get('/enforcer/partial', [AddEnforcer::class, 'partial'])->name('enforcer.partial');
    Route::resource('enforcer', AddEnforcer::class);
    Route::get('enforcer/{enforcer}/json', [AddEnforcer::class, 'json'])
     ->name('enforcer.json');

     //Violator routes
    Route::get('/violatorTable/partial', [ViolatorTableController::class, 'partial'])->name('violatorTable.partial');
    Route::resource('violatorTable', ViolatorTableController::class);

    Route::post('ticket/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('ticket.updateStatus');
    Route::get('ticket/partial', [AdminTicketController::class, 'partial'])->name('ticket.partial');
    Route::resource('ticket', AdminTicketController::class);

});

Route::middleware('violator')->group(function () {
    Route::get('/vdash', [ViolatorManagementController::class,'violatorDash'])->name('violator.dashboard');
    Route::get('/violator/password/change', [ViolatorAuthController::class, 'showChangePasswordForm'])->name('violator.password.change');
    Route::post('/violator/password/change', [ViolatorAuthController::class, 'changePassword'])->name('violator.password.update');
});

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
