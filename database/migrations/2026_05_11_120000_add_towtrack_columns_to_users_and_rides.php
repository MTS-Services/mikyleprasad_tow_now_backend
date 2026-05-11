<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->text('fcm_token')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'current_lat')) {
                $table->decimal('current_lat', 10, 8)->nullable()->after('fcm_token');
            }
            if (! Schema::hasColumn('users', 'current_lng')) {
                $table->decimal('current_lng', 11, 8)->nullable()->after('current_lat');
            }
            if (! Schema::hasColumn('users', 'location_updated_at')) {
                $table->timestamp('location_updated_at')->nullable()->after('current_lng');
            }
        });

        Schema::table('rides', function (Blueprint $table): void {
            if (! Schema::hasColumn('rides', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 8)->nullable()->after('pickup_location');
            }
            if (! Schema::hasColumn('rides', 'pickup_lng')) {
                $table->decimal('pickup_lng', 11, 8)->nullable()->after('pickup_lat');
            }
            if (! Schema::hasColumn('rides', 'dropoff_lat')) {
                $table->decimal('dropoff_lat', 10, 8)->nullable()->after('dropoff_location');
            }
            if (! Schema::hasColumn('rides', 'dropoff_lng')) {
                $table->decimal('dropoff_lng', 11, 8)->nullable()->after('dropoff_lat');
            }
            if (! Schema::hasColumn('rides', 'offline_temp_id')) {
                $table->string('offline_temp_id', 64)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('rides', 'synced_from_offline')) {
                $table->boolean('synced_from_offline')->default(false)->after('offline_temp_id');
            }
            if (! Schema::hasColumn('rides', 'problem_type')) {
                $table->string('problem_type', 64)->nullable()->after('synced_from_offline');
            }
            if (! Schema::hasColumn('rides', 'problem_description')) {
                $table->text('problem_description')->nullable()->after('problem_type');
            }
            if (! Schema::hasColumn('rides', 'estimated_price')) {
                $table->decimal('estimated_price', 8, 2)->nullable()->after('problem_description');
            }
            if (! Schema::hasColumn('rides', 'final_price')) {
                $table->decimal('final_price', 8, 2)->nullable()->after('estimated_price');
            }
            if (! Schema::hasColumn('rides', 'payment_status')) {
                $table->string('payment_status', 32)->nullable()->after('final_price');
            }
        });

        Schema::table('rides', function (Blueprint $table): void {
            $table->index(['user_id', 'offline_temp_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'offline_temp_id']);
        });

        Schema::table('rides', function (Blueprint $table): void {
            $cols = [
                'pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng',
                'offline_temp_id', 'synced_from_offline', 'problem_type', 'problem_description',
                'estimated_price', 'final_price', 'payment_status',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('rides', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            foreach (['fcm_token', 'current_lat', 'current_lng', 'location_updated_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
