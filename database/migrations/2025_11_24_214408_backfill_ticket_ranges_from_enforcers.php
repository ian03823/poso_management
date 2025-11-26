<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // for each enforcer that has a range, create an initial batch row
        $enforcers = DB::table('enforcers')
            ->whereNotNull('ticket_start')
            ->whereNotNull('ticket_end')
            ->get();

        $now = now();

        $rows = $enforcers->map(function ($e) use ($now) {
            return [
                'badge_num'    => $e->badge_num,
                'ticket_start' => $e->ticket_start,
                'ticket_end'   => $e->ticket_end,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        })->toArray();

        if (!empty($rows)) {
            DB::table('ticket_range')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enforcers', function (Blueprint $table) {
            //
        });
    }
};
