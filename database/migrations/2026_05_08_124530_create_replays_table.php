<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->nullable()->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('replays')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replays');
    }
};
