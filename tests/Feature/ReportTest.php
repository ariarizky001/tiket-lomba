<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_terjual_counts_both_paid_and_unpaid_invoices()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 100, 'harga' => 100000]);

        $invoice1 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 10,
            'harga' => 100000,
        ]);

        $invoice2 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'belum_lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 15,
            'harga' => 100000,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('sesi.0.terjual', 25)
            ->assertJsonPath('sesi.0.sisa', 75);
    }

    public function test_pemasukan_only_from_paid_invoices()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);

        $invoice1 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 5,
            'harga' => 100000,
            'subtotal' => 500000,
        ]);

        $invoice2 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'belum_lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 3,
            'harga' => 100000,
            'subtotal' => 300000,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('ringkasan.pemasukan', 500000)
            ->assertJsonPath('ringkasan.belum_lunas', 300000);
    }

    public function test_pemasukan_uses_price_snapshot()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);

        $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 2,
            'harga' => 100000,
            'subtotal' => 200000,
        ]);

        $session->update(['harga' => 150000]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('ringkasan.pemasukan', 200000);
    }

    public function test_total_hadiah_and_laba_calculation()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);
        Prize::factory()->create(['event_session_id' => $session->id, 'juara' => 1, 'nominal' => 500000]);
        Prize::factory()->create(['event_session_id' => $session->id, 'juara' => 2, 'nominal' => 300000]);

        $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 10,
            'harga' => 100000,
            'subtotal' => 1000000,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('sesi.0.total_hadiah', 800000)
            ->assertJsonPath('sesi.0.selisih', 200000)
            ->assertJsonPath('ringkasan.total_hadiah', 800000)
            ->assertJsonPath('ringkasan.laba', 200000)
            ->assertJsonPath('ringkasan.jumlah_invoice', 1);
    }

    public function test_sessions_without_sales_show_zero()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 100]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('sesi.0.terjual', 0)
            ->assertJsonPath('sesi.0.sisa', 100)
            ->assertJsonPath('sesi.0.total_hadiah', 0)
            ->assertJsonPath('sesi.0.selisih', 0);
    }

    public function test_anti_n1_query_constant_queries()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();

        foreach (range(1, 3) as $i) {
            $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000, 'nama' => "Session $i"]);
            $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => 1,
                'harga' => 100000,
            ]);
        }

        DB::enableQueryLog();
        $this->getJson("/api/events/{$event->id}/report");
        $queries3 = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(4, 15) as $i) {
            $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000, 'nama' => "Session $i"]);
            $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'event_session_id' => $session->id,
                'qty' => 1,
                'harga' => 100000,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$event->id}/report");
        $queries15 = count(DB::getQueryLog());

        $this->assertEquals($queries3, $queries15, 'Query count should remain constant as data grows');
    }

    public function test_report_includes_all_sessions()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        EventSession::factory()->count(5)->create(['event_id' => $event->id]);

        $response = $this->getJson("/api/events/{$event->id}/report");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'sesi');
    }
}
