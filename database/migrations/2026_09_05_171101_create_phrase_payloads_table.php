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
        Schema::create('phrase_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phrase_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('idiomatic_translation', 255);
            $table->jsonb('payload_data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phrase_payloads');
    }
};
