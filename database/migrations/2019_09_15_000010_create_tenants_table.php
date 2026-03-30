<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id')->primary();

            // Link to owner
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
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
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
