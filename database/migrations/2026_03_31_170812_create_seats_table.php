<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();

            // 🔗 Relation to Room
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('seat_number'); // A1, B2
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // 🔥 IMPORTANT: Prevent duplicate seats in same room
            $table->unique(['room_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
