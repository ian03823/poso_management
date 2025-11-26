<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_range', function (Blueprint $table) {
            $table->id();
            $table->string('badge_num')->index();
            $table->unsignedBigInteger('ticket_start');
            $table->unsignedBigInteger('ticket_end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_range');
    }
};
