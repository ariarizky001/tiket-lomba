<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RekapPesertaTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();
    }

    public function test_endpoint_returns_401_without_login()
    {
        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        $response->assertStatus(401);
    }

    public function test_endpoint_returns_404_for_nonexistent_event()
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/events/999999/rekap-peserta');
        $response->assertStatus(404);
    }

    public function test_basic_structure()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'nama' => 'Sesi A',
            'harga' => 100000,
            'kuota' => 24,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'sesi' => [
                    '*' => [
                        'id',
                        'nama',
                        'harga',
                        'kuota',
                        'peserta',
                    ],
                ],
            ]);
    }

    public function test_peserta_order_lunas_first_by_paid_at()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'kuota' => 24,
        ]);

        // Create invoices with different paid_at times
        $invoice1 = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
            'paid_at' => '2026-10-15 10:00:00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $invoice2 = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
            'paid_at' => '2026-10-15 09:00:00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $invoice3 = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'belum_lunas',
            'created_at' => '2026-10-15 11:00:00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice3->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $peserta = $response->json('sesi.0.peserta');
        
        // First should be invoice2 (paid_at 09:00)
        $this->assertEquals($invoice2->id, $peserta[0]['invoice_id']);
        // Second should be invoice1 (paid_at 10:00)
        $this->assertEquals($invoice1->id, $peserta[1]['invoice_id']);
        // Third should be invoice3 (belum lunas)
        $this->assertEquals($invoice3->id, $peserta[2]['invoice_id']);
        $this->assertEquals('belum_lunas', $peserta[2]['status']);
    }

    public function test_qty_split_into_multiple_rows()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'kuota' => 24,
        ]);

        $invoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
        ]);
        
        // Create invoice item with qty = 3
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 3,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $peserta = $response->json('sesi.0.peserta');
        
        // Should have 3 rows with same name
        $this->assertCount(3, $peserta);
        $this->assertEquals($invoice->id, $peserta[0]['invoice_id']);
        $this->assertEquals($invoice->id, $peserta[1]['invoice_id']);
        $this->assertEquals($invoice->id, $peserta[2]['invoice_id']);
        $this->assertEquals($peserta[0]['nama'], $peserta[1]['nama']);
        $this->assertEquals($peserta[1]['nama'], $peserta[2]['nama']);
        $this->assertEquals('lunas', $peserta[0]['status']);
    }

    public function test_belum_lunas_after_lunas()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'kuota' => 24,
        ]);

        $lunasInvoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $lunasInvoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $belumInvoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'belum_lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $belumInvoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $peserta = $response->json('sesi.0.peserta');
        
        $this->assertEquals('lunas', $peserta[0]['status']);
        $this->assertEquals('belum_lunas', $peserta[1]['status']);
    }

    public function test_sesi_without_peserta_returns_empty_array()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'kuota' => 24,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $response->assertStatus(200);
        $this->assertEquals([], $response->json('sesi.0.peserta'));
    }

    public function test_status_only_lunas_or_belum_lunas()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
        ]);

        $invoice1 = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $invoice2 = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'belum_lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $peserta = $response->json('sesi.0.peserta');
        
        foreach ($peserta as $p) {
            $this->assertContains($p['status'], ['lunas', 'belum_lunas']);
        }
    }

    public function test_harga_and_kuota_are_integers()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'harga' => 150000,
            'kuota' => 30,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $this->assertEquals(150000, $response->json('sesi.0.harga'));
        $this->assertEquals(30, $response->json('sesi.0.kuota'));
        $this->assertIsInt($response->json('sesi.0.harga'));
        $this->assertIsInt($response->json('sesi.0.kuota'));
    }

    public function test_no_n1_queries()
    {
        Sanctum::actingAs($this->user);
        
        $sessions = EventSession::factory()->count(3)->create([
            'event_id' => $this->event->id,
        ]);

        foreach ($sessions as $session) {
            $invoice = Invoice::factory()->create([
                'event_id' => $this->event->id,
                'status' => 'lunas',
            ]);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => 2,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        $queries3 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $sessions2 = EventSession::factory()->count(15)->create([
            'event_id' => $this->event->id,
        ]);

        foreach ($sessions2 as $session) {
            $invoice = Invoice::factory()->create([
                'event_id' => $this->event->id,
                'status' => 'lunas',
            ]);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => 2,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        $queries18 = count(DB::getQueryLog());

        $this->assertEquals($queries3, $queries18, 'Query count should remain constant');
    }

    public function test_event_not_found_returns_404()
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/events/999999/rekap-peserta');
        $response->assertStatus(404);
    }

    public function test_only_lunas_and_belum_lunas_invoices_are_included()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create([
            'event_id' => $this->event->id,
        ]);

        // Create invoices with different statuses - some should be excluded
        $lunasInvoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $lunasInvoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $belumInvoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'belum_lunas',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $belumInvoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        // This should be excluded
        $cancelledInvoice = Invoice::factory()->create([
            'event_id' => $this->event->id,
            'status' => 'cancelled',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $cancelledInvoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        
        $peserta = $response->json('sesi.0.peserta');
        $this->assertCount(2, $peserta);
        
        $invoiceIds = array_column($peserta, 'invoice_id');
        $this->assertNotContains($cancelledInvoice->id, $invoiceIds);
    }
}