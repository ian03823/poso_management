<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class DiagnosticsController extends Controller
{
    //
    public function index()
    {
        $checks = [];

        // DB
        try {
            $dbName  = DB::getDatabaseName();
            $version = optional(DB::selectOne('select version() as v'))->v;
            DB::select('select 1');
            $checks['db'] = ['ok' => true, 'database' => $dbName, 'version' => $version, 'connection' => config('database.default')];
        } catch (\Throwable $e) {
            $checks['db'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        // Cache
        try {
            Cache::put('diag', 'ok', 60);
            $checks['cache'] = ['ok' => Cache::get('diag') === 'ok', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            $checks['cache'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        // Storage (public)
        try {
            Storage::disk('public')->put('diag.txt', (string) now());
            $checks['storage'] = [
                'ok' => true,
                'disk' => config('filesystems.default'),
                'symlink' => is_link(public_path('storage')),
                'sample_url' => asset('storage/diag.txt'),
            ];
        } catch (\Throwable $e) {
            $checks['storage'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        $app = [
            'laravel'  => app()->version(),
            'php'      => PHP_VERSION,
            'env'      => config('app.env'),
            'debug'    => config('app.debug'),
            'app_url'  => config('app.url'),
        ];

        return view('admin.diagnostics.index', compact('checks','app'));
    }

}
