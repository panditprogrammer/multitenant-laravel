<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('razorpay_key_id')->nullable()->after('library_id');
            $table->text('razorpay_key_secret')->nullable()->after('razorpay_key_id');
            $table->text('razorpay_webhook_secret')->nullable()->after('razorpay_key_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'razorpay_key_id',
                'razorpay_key_secret',
                'razorpay_webhook_secret',
            ]);
        });
    }
};
