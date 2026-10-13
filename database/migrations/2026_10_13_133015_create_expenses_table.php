<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->string('kategori', 30);
            $table->string('keterangan', 255);
            $table->string('penerima', 150)->nullable();
            $table->unsignedInteger('nominal');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
