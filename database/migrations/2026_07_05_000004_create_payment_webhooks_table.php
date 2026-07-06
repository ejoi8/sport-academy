<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->index();
            $table->string('gateway')->index();
            $table->string('event_key');
            $table->boolean('verified')->default(false);
            $table->string('resolved_status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('payment-gateway.store.tables.webhooks', 'payment_webhooks');
    }
};
