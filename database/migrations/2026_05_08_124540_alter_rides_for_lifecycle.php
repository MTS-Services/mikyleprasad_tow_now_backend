<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->unsignedInteger('eta_minutes')->nullable()->after('notes');
            $table->string('eta_reason')->nullable()->after('eta_minutes');

            $table->string('cancel_reason')->nullable()->after('eta_reason');
            $table->string('cancelled_by', 20)->nullable()->after('cancel_reason');

            $table->timestamp('accepted_at')->nullable()->after('expired_at');
            $table->timestamp('arrived_at')->nullable()->after('accepted_at');
            $table->timestamp('picked_up_at')->nullable()->after('arrived_at');
            $table->timestamp('completion_requested_at')->nullable()->after('picked_up_at');
            $table->timestamp('completed_at')->nullable()->after('completion_requested_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            $table->unsignedInteger('total_arrival_minutes')->nullable()->after('total_ride_time');
            $table->unsignedInteger('total_ride_minutes')->nullable()->after('total_arrival_minutes');

            $table->index(['driver_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expired_at');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->dropIndex(['driver_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['expired_at']);

            $table->dropColumn([
                'eta_minutes',
                'eta_reason',
                'cancel_reason',
                'cancelled_by',
                'accepted_at',
                'arrived_at',
                'picked_up_at',
                'completion_requested_at',
                'completed_at',
                'cancelled_at',
                'total_arrival_minutes',
                'total_ride_minutes',
            ]);
        });
    }
};
