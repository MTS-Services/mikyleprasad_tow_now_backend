<?php

use App\Enums\RideStatusEnum;
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
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('driver_id');
            $table->string('status')->default(RideStatusEnum::REQUESTED->value);

            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->text('notes')->nullable();


            $table->string('total_arrival_time')->nullable()->comment('in minutes');
            $table->string('total_ride_time')->nullable()->comment('in minutes');
            
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();


            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
