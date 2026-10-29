<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Prize;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function test_no_quota_exceeded()
    {
        // Create session and sell exactly up to quota
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'kuota' => 10]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 10,
            'harga' => 100000,
            'subtotal' => 1000000,
        ]);

        // Try to add one more ticket
        $invoice2 = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        $response = $this->postJson("/api/events/{$this->event->id}/invoices", [
            'nama_peserta' => 'Test User',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 1],
            ],
        ]);

        $response->assertStatus(422);

        // Verify database integrity: terjual <= kuota
        $violations = DB::select("
            SELECT s.id, s.nama, s.kuota, COALESCE(SUM(ii.qty),0) AS terjual
            FROM event_sessions s 
            LEFT JOIN invoice_items ii ON ii.event_session_id = s.id
            GROUP BY s.id, s.nama, s.kuota 
            HAVING terjual > s.kuota
        ");

        $this->assertEmpty($violations, 'Tidak boleh ada sesi dengan terjual > kuota');
    }

    /** @test */
    public function test_invoice_total_matches_items_subtotal_sum()
    {
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 150000]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'total' => 450000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 3,
            'harga' => 150000,
            'subtotal' => 450000,
        ]);

        $mismatches = DB::select("
            SELECT i.id, i.total, SUM(ii.subtotal) AS dihitung
            FROM invoices i 
            JOIN invoice_items ii ON ii.invoice_id = i.id
            GROUP BY i.id, i.total 
            HAVING i.total <> dihitung
        ");

        $this->assertEmpty($mismatches, 'Total invoice harus sama dengan jumlah subtotal item');
    }

    /** @test */
    public function test_no_invoices_without_items()
    {
        // Create invoice without items directly in DB (bypassing API validation)
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
        // Verify the integrity check query catches this anomaly
        $emptyInvoices = DB::select("
            SELECT i.id 
            FROM invoices i 
            LEFT JOIN invoice_items ii ON ii.invoice_id = i.id 
            WHERE ii.id IS NULL
        ");

        $this->assertNotEmpty($emptyInvoices, 'Integrity check harus mendeteksi invoice tanpa item');
        
        // Clean up for other tests
        $invoice->delete();
        
        // Now verify no more empty invoices
        $emptyInvoicesAfter = DB::select("
            SELECT i.id 
            FROM invoices i 
            LEFT JOIN invoice_items ii ON ii.invoice_id = i.id 
            WHERE ii.id IS NULL
        ");
        $this->assertEmpty($emptyInvoicesAfter, 'Setelah dibersihkan, tidak boleh ada invoice tanpa item');
    }

    /** @test */
    public function test_item_subtotal_matches_qty_times_harga()
    {
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 200000]);
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
        
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 2,
            'harga' => 200000,
            'subtotal' => 400000,
        ]);

        $mismatches = DB::select("
            SELECT id 
            FROM invoice_items 
            WHERE subtotal <> qty * harga
        ");

        $this->assertEmpty($mismatches, 'Subtotal item harus sama dengan qty * harga');
    }

    /** @test */
    public function test_status_and_paid_at_synchronized()
    {
        $invoiceLunas = Invoice::factory()->create([
            'event_id' => $this->event->id, 
            'status' => 'lunas', 
            'paid_at' => now()
        ]);
        
        $invoiceBelumLunas = Invoice::factory()->create([
            'event_id' => $this->event->id, 
            'status' => 'belum_lunas', 
            'paid_at' => null
        ]);

        $outOfSync = DB::select("
            SELECT id 
            FROM invoices
            WHERE (status = 'lunas' AND paid_at IS NULL) 
               OR (status = 'belum_lunas' AND paid_at IS NOT NULL)
        ");

        $this->assertEmpty($outOfSync, 'Status dan paid_at harus sinkron');
    }

    /** @test */
    public function test_invoice_kode_and_nomor_format()
    {
        $event = Event::factory()->create(['nama' => 'Test Event']);
        $session = EventSession::factory()->create(['event_id' => $event->id]);
        
        $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'Test',
            'items' => [['event_session_id' => $session->id, 'qty' => 1]],
        ]);

        $anomalies = DB::select("
            SELECT id, kode, nomor 
            FROM invoices
            WHERE kode NOT REGEXP '^[A-Z0-9]{1,4}-[0-9]{4,}$'
               OR CAST(SUBSTRING_INDEX(kode, '-', -1) AS UNSIGNED) <> nomor
        ");

        $this->assertEmpty($anomalies, 'Format kode invoice harus KODE-XXXX dan nomor harus cocok');
    }

    /** @test */
    public function test_no_cross_event_session_items()
    {
        $event2 = Event::factory()->create();
        $session1 = EventSession::factory()->create(['event_id' => $this->event->id]);
        $session2 = EventSession::factory()->create(['event_id' => $event2->id]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session1->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        // This would be invalid - item pointing to session from different event
        // We verify the constraint doesn't exist in DB
        $crossEvents = DB::select("
            SELECT ii.id 
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            JOIN event_sessions s ON s.id = ii.event_session_id
            WHERE s.event_id <> i.event_id
        ");

        $this->assertEmpty($crossEvents, 'Item tidak boleh menunjuk sesi dari event lain');
    }

    /** @test */
    public function test_prizes_validity()
    {
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        // Create valid prize
        Prize::factory()->create([
            'event_session_id' => $session->id,
            'juara' => 1,
            'nominal' => 500000,
        ]);

        // Insert invalid prize directly to test integrity check query
        DB::table('prizes')->insert([
            'event_session_id' => $session->id,
            'juara' => 6, // Invalid juara
            'nominal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invalid = DB::select("
            SELECT id 
            FROM prizes 
            WHERE juara NOT BETWEEN 1 AND 5 OR nominal < 1
        ");

        $this->assertNotEmpty($invalid, 'Integrity check harus mendeteksi juara > 5');

        // Clean up invalid prize
        DB::table('prizes')->where('juara', 6)->delete();

        // Verify query returns empty now
        $invalidAfter = DB::select("
            SELECT id 
            FROM prizes 
            WHERE juara NOT BETWEEN 1 AND 5 OR nominal < 1
        ");

        $this->assertEmpty($invalidAfter, 'Setelah dibersihkan, hadiah harus validsemua');
    }

    /** @test */
    public function test_expenses_validity()
    {
        Expense::factory()->create([
            'event_id' => $this->event->id,
            'nominal' => 100000,
            'deleted_at' => null,
        ]);

        $invalid = DB::select("
            SELECT id 
            FROM expenses 
            WHERE deleted_at IS NULL AND nominal < 1
        ");

        $this->assertEmpty($invalid, 'Pengeluaran aktif harus nominal >= 1');
    }

    /** @test */
    public function test_cross_report_and_bookkeeping_pemasukan_matches()
    {
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 100000]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 2,
            'harga' => 100000,
            'subtotal' => 200000,
        ]);

        // Get pemasukan from manual query
        $manualPemasukan = DB::select("
            SELECT i.event_id, SUM(ii.subtotal) AS pemasukan_lunas
            FROM invoices i 
            JOIN invoice_items ii ON ii.invoice_id = i.id
            WHERE i.status = 'lunas' 
            GROUP BY i.event_id
        ");

        // Get pemasukan from ReportController logic
        $reportPemasukan = DB::select("
            SELECT COALESCE(SUM(CASE WHEN invoices.status = 'lunas' THEN invoice_items.subtotal ELSE 0 END), 0) as pemasukan
            FROM event_sessions
            LEFT JOIN invoice_items ON event_sessions.id = invoice_items.event_session_id
            LEFT JOIN invoices ON invoice_items.invoice_id = invoices.id
            WHERE event_sessions.event_id = ?
            GROUP BY event_sessions.event_id
        ", [$this->event->id]);

        $this->assertEquals(
            $manualPemasukan[0]->pemasukan_lunas ?? 0,
            $reportPemasukan[0]->pemasukan ?? 0,
            'Pemasukan Laporan harus sama dengan query manual'
        );
    }
}