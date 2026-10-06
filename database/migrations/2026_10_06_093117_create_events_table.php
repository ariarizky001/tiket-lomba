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
         Schema::create('events', function (Blueprint $table) {
             $table->id();
             $table->string('nama', 150);
             $table->date('tanggal');
             $table->string('lokasi', 150)->nullable();
             $table->boolean('is_active')->default(true);
             $table->timestamps();
         });
     }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
