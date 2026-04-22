<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete(); // Each room belongs to a library
            $table->string('name'); // Hall 1, Room 2  // Optional: prevent duplicate room names (per tenant DB)
            $table->string('floor')->nullable(); // Ground, 1st Floor
            $table->boolean('is_active')->default(true);
            $table->enum('type', ['NORMAL', 'AC'])->default('NORMAL');
            $table->boolean('has_wifi')->default(false);
            $table->timestamps();
            $table->unique(['library_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
