<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('email'); // owner, vendor, admin
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('closing_time');
            $table->timestamp('blocked_at')->nullable()->after('trial_ends_at');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'blocked_at', 'blocked_reason']);
        });
    }
};