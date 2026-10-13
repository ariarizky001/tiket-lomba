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

class ParticipantRecapTest extends TestCase
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

    public function test_get_participant_recap_basic()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'nama' => 'Sesi A']);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Budi', 'status' => 'lunas', 'total' => 250000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 2,
            'harga' => 125000,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        
        $response->assertStatus(200)
            ->assertJsonPath('sesi.0.nama', 'Sesi A')
            ->assertJsonPath('sesi.0.summary.total_peserta', 2)
            ->assertJsonPath('sesi.0.summary.total_qty', 2)
            ->assertJsonPath('sesi.0.summary.total_revenue_lunas', 250000)
            ->assertJsonPath('sesi.0.summary.total_revenue_belum_lunas', 0)
            ->assertJsonCount(2, 'sesi.0.peserta');
    }

    public function test_expand_qty_to_separate_rows()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Budi', 'status' => 'lunas', 'total' => 300000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 3,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        
        $peserta = $response->json('sesi.0.peserta');
        $this->assertCount(3, $peserta);
        $this->assertEquals('Budi', $peserta[0]['nama_peserta']);
        $this->assertEquals(1, $peserta[0]['urutan']);
        $this->assertEquals('Budi', $peserta[1]['nama_peserta']);
        $this->assertEquals(2, $peserta[1]['urutan']);
        $this->assertEquals('Budi', $peserta[2]['nama_peserta']);
        $this->assertEquals(3, $peserta[2]['urutan']);
    }

    public function test_multiple_peserta_per_sesi()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        $invoice1 = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Budi', 'status' => 'lunas', 'total' => 200000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 2,
        ]);
        
        $invoice2 = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Sari', 'status' => 'belum_lunas', 'total' => 100000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        
        $peserta = $response->json('sesi.0.peserta');
        $this->assertCount(3, $peserta);
        $this->assertEquals('Budi', $peserta[0]['nama_peserta']);
        $this->assertEquals('Budi', $peserta[1]['nama_peserta']);
        $this->assertEquals('Sari', $peserta[2]['nama_peserta']);
    }

    public function test_multiple_sessions()
    {
        Sanctum::actingAs($this->user);
        
        $session1 = EventSession::factory()->create(['event_id' => $this->event->id, 'nama' => 'Sesi A']);
        $session2 = EventSession::factory()->create(['event_id' => $this->event->id, 'nama' => 'Sesi B']);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Budi', 'status' => 'lunas', 'total' => 300000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session1->id,
            'qty' => 2,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session2->id,
            'qty' => 1,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        
        $this->assertCount(2, $response->json('sesi'));
        $this->assertCount(2, $response->json('sesi.0.peserta'));
        $this->assertCount(1, $response->json('sesi.1.peserta'));
    }

    public function test_status_summary_calculation()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        $invoice1 = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Budi', 'status' => 'lunas', 'total' => 200000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 200000,
            'subtotal' => 200000,
        ]);
        
        $invoice2 = Invoice::factory()->create(['event_id' => $this->event->id, 'nama_peserta' => 'Sari', 'status' => 'belum_lunas', 'total' => 150000]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 150000,
            'subtotal' => 150000,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        
        $response->assertJsonPath('sesi.0.summary.total_revenue_lunas', 200000)
            ->assertJsonPath('sesi.0.summary.total_revenue_belum_lunas', 150000)
            ->assertJsonPath('sesi.0.summary.total_peserta', 2);
    }

    public function test_protected_endpoint_requires_auth()
    {
        $response = $this->getJson("/api/events/{$this->event->id}/participant-recap");
        $response->assertStatus(401);
    }

    public function test_no_n1_queries()
    {
        Sanctum::actingAs($this->user);
        
        $session1 = EventSession::factory()->create(['event_id' => $this->event->id]);
        $session2 = EventSession::factory()->create(['event_id' => $this->event->id]);
        $session3 = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        foreach ([$session1, $session2, $session3] as $session) {
            for ($i = 0; $i < 2; $i++) {
                $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
                InvoiceItem::factory()->create([
                    'invoice_id' => $invoice->id,
                    'event_session_id' => $session->id,
                    'qty' => 2,
                ]);
            }
        }
        
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/participant-recap");
        $queries3 = count(DB::getQueryLog());
        DB::disableQueryLog();
        
        $session4 = EventSession::factory()->create(['event_id' => $this->event->id]);
        $session5 = EventSession::factory()->create(['event_id' => $this->event->id]);
        
        for ($i = 0; $i < 2; $i++) {
            $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session4->id,
                'qty' => 2,
            ]);
            $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session5->id,
                'qty' => 2,
            ]);
        }
        
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/participant-recap");
        $queries5 = count(DB::getQueryLog());
        
        $this->assertEquals($queries3, $queries5, 'Query count should remain constant');
    }
}
