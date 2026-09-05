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
        Schema::create('phrases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_text', 150);
            $table->string('source_language', 5);
            $table->string('status', 50);
            $table->string('tag', 50)->nullable();
            $table->unsignedTinyInteger('queue_position')->nullable();
            $table->unsignedTinyInteger('success_streak')->default(0);
            $table->timestamp('learned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_phrases_active_window');
            $table->index(['status', 'learned_at'], 'idx_phrases_audit_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phrases');
    }
};
