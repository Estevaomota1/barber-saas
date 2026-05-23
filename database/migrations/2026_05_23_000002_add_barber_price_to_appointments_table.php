<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('barber_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 8, 2)->default(0);
            $table->string('service_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['barber_id']);
            $table->dropColumn(['barber_id', 'price', 'service_name']);
        });
    }
};