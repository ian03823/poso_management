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
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->string('violation_code')->unique();
            $table->string('violation_name');
            $table->decimal('fine_amount', 8, 2);
            $table->integer('penalty_points');
            $table->text('description')->nullable();
            $table->enum('category', [
                'Moving Violations', 
                'Non-Moving Violations', 
                'Safety Violations', 
                'Parking Violations'
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
