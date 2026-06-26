<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Apenas adiciona as colunas sem unique() para evitar conflito com NULL
            if (!Schema::hasColumn('appointments', 'cancel_token')) {
                $table->uuid('cancel_token')->nullable()->after('id');
            }
            if (!Schema::hasColumn('appointments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('appointments', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'cancel_token')) {
                $table->dropColumn('cancel_token');
            }
            if (Schema::hasColumn('appointments', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('appointments', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};