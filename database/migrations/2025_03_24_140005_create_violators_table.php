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
        Schema::create('violators', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 225);
            $table->string('middle_name', 225);
            $table->string('last_name', 225);
            $table->string('address');
            $table->string('phone')->nullable;
            $table->date('birthdate')->nullable();
            $table->string('license_number')->nullable();
            $table->string('username')->unique()->nullable();
            $table->string('password', 225)->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violators');
    }
};
