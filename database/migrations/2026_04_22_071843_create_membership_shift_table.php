<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_shift', function (Blueprint $table) {
            $table->id();

            // 🔗 relationships
            $table->foreignId('membership_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('shift_id')
                ->constrained()
                ->cascadeOnDelete();

            // 🔥 prevent duplicate same shift
            $table->unique(['membership_id', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_shift');
    }
};