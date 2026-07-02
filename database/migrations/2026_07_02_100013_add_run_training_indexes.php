<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roster load filters enrolments by offering + status.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['offering_id', 'status']);
        });

        // The month/timeslot dropdowns filter open offerings by period.
        Schema::table('offerings', function (Blueprint $table) {
            $table->index(['is_open', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['offering_id', 'status']);
        });

        Schema::table('offerings', function (Blueprint $table) {
            $table->dropIndex(['is_open', 'period']);
        });
    }
};
