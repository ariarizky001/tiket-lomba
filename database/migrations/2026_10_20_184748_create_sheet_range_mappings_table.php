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
        Schema::create('sheet_range_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->integer('session_index');
            $table->string('sheet_range');
            $table->string('sheet_name')->default('Daftar Peserta');
            $table->timestamps();
            $table->unique(['event_id', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_range_mappings');
    }
};
