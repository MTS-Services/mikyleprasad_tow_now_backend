<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('rate', 20, 10);
            $table->timestamp('effective_at');
            $table->string('source', 16);
            $table->uuid('sync_batch_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['base_currency_id', 'quote_currency_id', 'effective_at'], 'currency_rates_pair_effective_idx');
            $table->index('sync_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
