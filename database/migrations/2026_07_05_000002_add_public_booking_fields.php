<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('source')->default('admin')->after('credits_expire_at');
            $table->string('booking_reference')->nullable()->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['source', 'booking_reference']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
