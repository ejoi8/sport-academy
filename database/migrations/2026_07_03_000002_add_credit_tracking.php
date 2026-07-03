<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which enrolment's session credit this attendance consumed. Null = a walk-in
        // (paid a fee, consumed no credit).
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });

        // When an enrolment's unused credits lapse. Null = never expire (the default policy).
        Schema::table('enrollments', function (Blueprint $table) {
            $table->date('credits_expire_at')->nullable()->after('sessions_included');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrollment_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('credits_expire_at');
        });
    }
};
