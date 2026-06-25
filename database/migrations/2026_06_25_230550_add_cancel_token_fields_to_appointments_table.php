<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('cancel_token')->nullable()->unique()->after('id');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['cancel_token', 'cancelled_at', 'cancel_reason']);
        });
    }
};