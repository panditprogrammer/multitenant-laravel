<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('library_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->index(['owner_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
