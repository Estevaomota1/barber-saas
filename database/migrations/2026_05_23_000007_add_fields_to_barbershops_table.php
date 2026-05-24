<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('opening_time')->default('09:00');
            $table->string('closing_time')->default('18:00');
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropColumn(['slug', 'phone', 'address', 'description', 'opening_time', 'closing_time']);
        });
    }
};