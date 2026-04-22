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
        Schema::create('libraries', function (Blueprint $table) {
            $table->id();

            // Link to owner
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Library Info
            $table->string('name');
            // Contact Info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            // Address Info
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('google_map_link')->nullable();
            // Media
            $table->string('profile_image')->nullable();
            // Status
            $table->boolean('is_active')->default(true);
            $table->time('open_time')->default('06:00:00');
            $table->time('close_time')->default('20:00:00');
            $table->decimal('normal_price', 8, 2)->nullable();
            $table->decimal('ac_price', 8, 2)->nullable();
            $table->string('currency')->default('INR');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libraries');
    }
};
