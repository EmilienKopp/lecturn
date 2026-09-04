<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_id')->constrained('presentations')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('reaction_counts');
            $table->unsignedInteger('reaction_total')->default(0);
            $table->json('viewers');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'started_at']);
            $table->index(['presentation_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_sessions');
    }
};
