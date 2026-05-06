<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uuid_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('conversation_uuid_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('order_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('transaction_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('payment_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        Schema::create('username_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uuid_sequences');
        Schema::dropIfExists('conversation_uuid_sequences');
        Schema::dropIfExists('order_sequences');
        Schema::dropIfExists('transaction_sequences');
        Schema::dropIfExists('payment_sequences');
        Schema::dropIfExists('username_sequences');
    }
};
