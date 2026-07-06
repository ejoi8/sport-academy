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
            $table->string('gateway')->index();
            $table->string('reference')->index();
            $table->string('gateway_reference')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->json('last_response')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('payment-gateway.store.tables.payments', 'payments');
    }
};
