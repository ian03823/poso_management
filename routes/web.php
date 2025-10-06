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
use App\Http\Controllers\ImpoundedController;
use App\Http\Controllers\ViolatorTableController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ViolatorEmailController;
use App\Http\Controllers\ViolatorForgotPasswordController;
use App\Http\Controllers\AdminForgotPasswordController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\ViolatorPhoneController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;


// Route::get('/', function () {
//     return view('welcome');
// });

/* health check used by isReallyOnline() */
Route::get('/ping', fn() => response()->noContent());

Route::get('/_diag/ocr', function () {
    // 1) Check actual files on disk (under public/vendor/tesseract)
    $files = [
        'vendor/tesseract/tesseract-core-simd-lstm.wasm.js',
        'vendor/tesseract/tesseract-core-simd-lstm.wasm',
        'vendor/tesseract/eng.traineddata.gz',
        'vendor/tesseract/tesseract.min.js',
        'vendor/tesseract/worker.min.js',
    ];
    $disk = [];
    foreach ($files as $rel) {
        $disk[$rel] = file_exists(public_path($rel)) ? 'OK (exists)' : 'MISSING';
    }

    // 2) Check the route endpoints over HTTP (should be 200 if routes are wired)
    $urls = [
        'simd_js'   => url('/wasm/tesseract-core-simd-lstm.wasm.js'),
        'simd_wasm' => url('/wasm/tesseract-core-simd-lstm.wasm'),
        'eng_gz'    => url('/wasm/eng.traineddata.gz'),
    ];
    $http = [];
    foreach ($urls as $k => $u) {
        try {
            $res = Http::timeout(8)->get($u);
            $http[$k] = 'HTTP ' . $res->status();
        } catch (\Throwable $e) {
            $http[$k] = 'ERROR: ' . $e->getMessage();
        }
    }

    return response()->json(['disk' => $disk, 'http' => $http, 'urls' => $urls]);
});



Route::get('/_diag/gas', function () {
    $url = config('otp.gmail_webapp.url');
    $secret = config('otp.gmail_webapp.secret');
    $to = request('to') ?: config('mail.from.address');

    try {
        $res = Http::timeout(15)->asJson()->post($url, [
            'secret'  => $secret,
            'to'      => $to,
            'subject' => 'POSO GAS test',
            'text'    => 'Hello from GAS test',
            'html'    => '<b>Hello</b> from GAS test',
        ]);
        return response("status={$res->status()} body={$res->body()}", 200, ['Content-Type'=>'text/plain']);
    } catch (\Throwable $e) {
        Log::error('GAS direct test failed', ['err'=>$e->getMessage()]);
        return 'EXCEPTION: '.$e->getMessage();
    }
});

// background sync JSON submit (CSRF exempt)
Route::post('/pwa/sync/ticket', [TicketController::class, 'storeJson'])->name('ticket.sync');


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
    // PWA start url should serve the Issue Ticket page
    Route::view('/offline','offline');
    Route::get('/pwa', [TicketController::class, 'create'])->name('pwa');
    Route::resource('enforcerCreate', EnforcerAcc::class);
    Route::resource('enforcerTicket', TicketController::class);
    Route::resource('enf', EnforcerManagementController::class);
    Route::get('/violators/check-license', [TicketController::class, 'checkLicense'])
        ->name('violators.checkLicense');
    Route::get('violators/suggestions', [TicketController::class, 'suggestions'])->name('enforcer.violators.suggestions');
    Route::get('violators/{id}', [TicketController::class, 'show'])->name('enforcer.violators.show');
    Route::get('enforcer/change/password', [EnforcerAuthController::class, 'showChangePassword'])->name('enforcer.password.edit');
    Route::post('enforcer/change/password', [EnforcerAuthController::class, 'changePassword'])->name('enforcer.password.update');
});

Route::get('/wasm/tesseract-core-simd-lstm.wasm.js', fn () =>
    response()->file(public_path('vendor/tesseract/tesseract-core-simd-lstm.wasm.js'), [
        'Content-Type'  => 'application/javascript',
        'Cache-Control' => 'public, max-age=31536000',
    ])
);
Route::get('/wasm/tesseract-core-simd-lstm.wasm', fn () =>
    response()->file(public_path('vendor/tesseract/tesseract-core-simd-lstm.wasm'), [
        'Content-Type'  => 'application/wasm',
        'Cache-Control' => 'public, max-age=31536000',
    ])
);

// Optional fallback to non-SIMD build if ever needed
Route::get('/wasm/tesseract-core.wasm', fn () =>
    response()->file(public_path('vendor/tesseract/tesseract-core.wasm'), [
        'Content-Type'  => 'application/wasm',
        'Cache-Control' => 'public, max-age=31536000',
    ])
);

Route::get('/wasm/eng.traineddata.gz', fn () =>
    response()->file(public_path('vendor/tesseract/eng.traineddata.gz'), [
        'Content-Type'  => 'application/gzip',
        'Cache-Control' => 'public, max-age=31536000',
    ])
);

// Admin protected routes
Route::middleware('admin')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class,'adminDash'])->name('admin.dashboard');
    Route::get('/admin/profile/update', [AdminDashboardController::class,'edit'])->name('admin.profile.edit');
    Route::put('/admin/profile/update', [AdminDashboardController::class,'update'])->name('admin.profile.update');
        
    //Violation routes
    Route::get('/violation/partial', [ViolationController::class,'partial'])->name('violation.partial');
    Route::resource('violation', ViolationController::class); 
    Route::get('violation/{violation}/json', [ViolationController::class, 'json'])
     ->name('violation.json');


    //Enforcer routes
    Route::get('/enforcer/partial', [AddEnforcer::class, 'partial'])->name('enforcer.partial');
    Route::resource('enforcer', AddEnforcer::class)->except(['destroy']);
    // soft-delete (deactivate)
    Route::delete('enforcer/{enforcer}', [AddEnforcer::class,'destroy'])->name('enforcer.destroy');
    Route::post('enforcer/{enforcer}/restore', [AddEnforcer::class,'restore'])->name('enforcer.restore');
    Route::get('enforcer/{enforcer}/json', [AddEnforcer::class, 'json'])
     ->name('enforcer.json');

     //Violator routes
    Route::get('/violatorTable/partial', [ViolatorTableController::class, 'partial'])->name('violatorTable.partial');
    Route::resource('violatorTable', ViolatorTableController::class);
    Route::post('paid/{ticket}/status', [ViolatorTableController::class, 'updateStatus'])
     ->name('ticket.updateStatus');

    //Ticket routes
    //Issue Ticket 
    Route::get('/violations/by-category', [AdminTicketController::class, 'violationsByCategory'])
    ->name('violations.byCategory');
    Route::get('/admin/tickets/create', [AdminTicketController::class, 'create'])
        ->name('admin.tickets.create');
    Route::post('/admin/tickets', [AdminTicketController::class, 'store'])
        ->name('admin.tickets.store');
    Route::get('ticket/partial', [AdminTicketController::class, 'partial'])->name('ticket.partial');
    Route::post('ticket/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('ticket.update.status');
    Route::resource('ticket', AdminTicketController::class);

    //Impound Vehicle routes
    Route::post('/impounded/resolve', [ImpoundedController::class, 'resolve'])->name('impound.resolve');
    Route::resource('impoundedVehicle', ImpoundedController::class);

    Route::get('dataAnalytics', [AnalyticsController::class,'index'])
     ->name('dataAnalytics.index');

    Route::get('dataAnalytics/latest', [AnalyticsController::class,'latest'])
        ->name('dataAnalytics.latest');
    // NEW: list tickets near a hotspot (for the modal)
    Route::get('dataAnalytics/hotspotTickets', [AnalyticsController::class,'hotspotTickets'])
        ->name('dataAnalytics.hotspotTickets');
    
    //ANALYTICS ROUTE
    Route::get('/logs/activity', [ActivityLogController::class, 'index'])->name('logs.activity');

    // On-demand report downloads
    Route::get('reports/download/{format}', [AnalyticsController::class,'download'])
        ->where('format','xlsx|docx')
        ->name('reports.download');

    Route::get('/superadmin/activity-logs', [ActivityLogController::class, 'index'])
        ->name('admin.activity-logs.index');
});

// 1) Logged-in violator, NOT necessarily phone-verified yet
Route::middleware('violator')->group(function () {
    Route::get('/vdash', [ViolatorManagementController::class,'violatorDash'])
        ->name('violator.dashboard');

    Route::get('/violator/email',  [ViolatorEmailController::class,'showForm'])->name('violator.email.show');
    Route::post('/violator/email', [ViolatorEmailController::class,'save'])->name('violator.email.save');
    Route::post('/violator/email/verify', [ViolatorEmailController::class,'verify'])->name('violator.email.verify');
    Route::post('/violator/email/resend', [ViolatorEmailController::class,'resend'])->name('violator.email.resend');
    // Allow password change before verification (your existing routes)

    Route::get('/violator/password/change', [ViolatorAuthController::class, 'showChangePasswordForm'])
        ->name('violator.password.change');

    Route::post('/violator/password/change', [ViolatorAuthController::class, 'changePassword'])
        ->name('violator.password.update');
});
// Forgot password (Violator) – multi-step
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/violator/password/forgot',  [ViolatorForgotPasswordController::class,'showRequest'])->name('violator.password.forgot.request');
    Route::post('/violator/password/forgot', [ViolatorForgotPasswordController::class,'submitEmail'])->name('violator.password.forgot.submit');
    Route::get('/violator/password/confirm', [ViolatorForgotPasswordController::class,'showConfirm'])->name('violator.password.forgot.confirm');
    Route::post('/violator/password/send-otp', [ViolatorForgotPasswordController::class,'sendOtp'])->name('violator.password.forgot.sendOtp');
    Route::get('/violator/password/enter-otp', [ViolatorForgotPasswordController::class,'showEnterOtp'])->name('violator.password.forgot.otp');
    Route::post('/violator/password/verify-otp', [ViolatorForgotPasswordController::class,'verifyOtp'])->name('violator.password.forgot.verify');
    Route::get('/violator/password/reset', [ViolatorForgotPasswordController::class,'showReset'])->name('violator.password.forgot.reset');
    Route::post('/violator/password/reset', [ViolatorForgotPasswordController::class,'reset'])->name('violator.password.forgot.update');
    
    Route::get('/admin/password/forgot',  [AdminForgotPasswordController::class, 'showRequest'])->name('admin.password.forgot.request');
    Route::post('/admin/password/forgot', [AdminForgotPasswordController::class, 'submitEmail'])->name('admin.password.forgot.submit');
    Route::get('/admin/password/reset',   [AdminForgotPasswordController::class, 'showReset'])->name('admin.password.reset.view');
    Route::post('/admin/password/reset',  [AdminForgotPasswordController::class, 'reset'])->name('admin.password.reset');
});
