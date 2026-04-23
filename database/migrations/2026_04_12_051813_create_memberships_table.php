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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();

            // 🔗 Relations
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();

            // user_id = student
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();

            // ⏰ Shift system (multi-shift support)
            $table->json('shift_ids');

            // 📅 Duration
            $table->date('start_date');
            $table->date('end_date');

            // 💰 Amount
            $table->decimal('amount', 8, 2);

            // 📊 Status
            $table->enum('status', ['active', 'expired', 'cancelled'])
                ->default('active');

            $table->timestamps();

            // ⚡ Indexes (important for performance)
            $table->index(['library_id', 'seat_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
