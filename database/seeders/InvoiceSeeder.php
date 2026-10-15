<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    // Sample participant names
    protected $names = [
        "RENDI SF", "DT KUDA TERBANG", "PUTRA KATONG", "ADAM FIKRI", "FENDI SALATIGA",
        "KJ PRIO MJS", "PAMAN DIKO", "GUS SHOLAH", "DR RIZAL", "ABAH HUDAN",
        "DEWANGGA", "H BETHET", "BLACK JAZZ", "ABAH KODI", "SANTOS BF",
        "DAWAM", "BINTANG PRADANA", "BIMA SBY", "RUDY NRS", "WILDAN MS",
        "JOKO SUSILO", "AGUS SETIAWAN", "BUDI SANTOSO", "CITRA DEWI",
    ];

    public function run(): void
    {
        $event = Event::first();

        if (!$event) {
            $this->command->error('Event not found. Please run EventSeeder first.');
            return;
        }

        $sessions = EventSession::where('event_id', $event->id)->get();

        if ($sessions->isEmpty()) {
            $this->command->error('No sessions found. Please run EventSessionSeeder first.');
            return;
        }

        $statuses = ['lunas', 'belum_lunas'];
        $invoiceCounter = 0;

        foreach ($sessions as $session) {
            // Shuffle names for each session to get different participants
            $shuffledNames = collect($this->names)->shuffle()->take(24)->values()->all();

            foreach ($shuffledNames as $index => $name) {
                $invoiceCounter++;
                
                // Random status with 70% chance of lunas
                $status = fake()->randomElement(array_merge(
                    array_fill(0, 7, 'lunas'),
                    array_fill(0, 3, 'belum_lunas')
                ));
                
                $paidAt = $status === 'lunas' ? Carbon::now()->subDays(fake()->numberBetween(0, 30)) : null;

                // Create invoice
                $invoice = Invoice::create([
                    'event_id' => $event->id,
                    'nomor' => $invoiceCounter,
                    'kode' => $this->generateInvoiceCode($event->nama, $invoiceCounter),
                    'nama_peserta' => $name,
                    'total' => $session->harga,
                    'status' => $status,
                    'paid_at' => $paidAt,
                ]);

                // Create invoice item linking to session
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'event_session_id' => $session->id,
                    'qty' => 1,
                    'harga' => $session->harga,
                    'subtotal' => $session->harga,
                ]);
            }
        }

        $this->command->info("Created 24 invoices for each of {$sessions->count()} sessions (Total: " . ($sessions->count() * 24) . " invoices)");
    }

    private function generateInvoiceCode($eventName, $number)
    {
        $words = explode(' ', $eventName);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper($word[0]);
        }
        $initials = preg_replace('/[^A-Z0-9]/', '', $initials);
        $initials = substr($initials, 0, 4);
        if (empty($initials)) {
            $initials = 'INV';
        }
        return $initials . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}