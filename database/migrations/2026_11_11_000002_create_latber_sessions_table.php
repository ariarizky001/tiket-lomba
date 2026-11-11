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
        Schema::create('latber_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('latber_id')->constrained('latbers')->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_urut');
            $table->string('nama', 150);
            $table->unsignedInteger('harga');
            $table->unsignedInteger('terjual')->default(0);
            $table->unsignedInteger('terpesan')->default(0);
            $table->timestamps();

            $table->unique(['latber_id', 'nama']);
            $table->unique(['latber_id', 'nomor_urut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('latber_sessions');
    }
};
