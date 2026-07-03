<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many sessions a monthly package grants. Configured on the program (the default),
        // snapshotted onto each offering (this month's product) and then onto the enrolment.
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_sessions')->default(4)->after('walk_in_fee_sen');
        });

        Schema::table('offerings', function (Blueprint $table) {
            $table->unsignedSmallInteger('session_count')->default(4)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('default_sessions');
        });

        Schema::table('offerings', function (Blueprint $table) {
            $table->dropColumn('session_count');
        });
    }
};
