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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('nomor');
            $table->string('kode', 20);
            $table->string('nama_peserta', 150);
            $table->unsignedInteger('total');
            $table->string('status', 15)->default('belum_lunas');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'nomor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
