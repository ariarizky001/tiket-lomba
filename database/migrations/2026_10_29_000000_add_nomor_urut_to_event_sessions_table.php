<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('nomor_urut')->nullable()->after('event_id');
        });

        // Seed initial values for existing sessions: per event, 1, 2, 3... in order of id
        $eventIds = DB::table('event_sessions')->distinct()->pluck('event_id');
        foreach ($eventIds as $eventId) {
            $sessions = DB::table('event_sessions')
                ->where('event_id', $eventId)
                ->orderBy('id', 'asc')
                ->get();

            $nomor = 1;
            foreach ($sessions as $session) {
                DB::table('event_sessions')
                    ->where('id', $session->id)
                    ->update(['nomor_urut' => $nomor++]);
            }
        }

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('nomor_urut')->nullable(false)->change();
            $table->unique(['event_id', 'nomor_urut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'nomor_urut']);
            $table->dropColumn('nomor_urut');
        });
    }
};
