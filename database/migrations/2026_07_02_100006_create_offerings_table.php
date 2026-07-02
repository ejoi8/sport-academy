<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('schedule_type')->default('recurring');
            $table->unsignedTinyInteger('weekday')->nullable(); // 1=Mon .. 7=Sun (ISO)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('specific_date')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('price_sen')->default(0);
            $table->foreignId('default_coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerings');
    }
};
