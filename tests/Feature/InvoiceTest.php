<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_invoice_with_two_sessions()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session1 = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);
        $session2 = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 150000]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John Doe',
            'items' => [
                ['event_session_id' => $session1->id, 'qty' => 2],
                ['event_session_id' => $session2->id, 'qty' => 3],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('total', 650000)
            ->assertJsonPath('items.0.subtotal', 200000)
            ->assertJsonPath('items.1.subtotal', 450000);
    }

    public function test_server_ignores_client_price_and_subtotal()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John Doe',
            'items' => [
                [
                    'event_session_id' => $session->id,
                    'qty' => 2,
                    'harga' => 50000,
                    'subtotal' => 100000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('items.0.harga', 100000)
            ->assertJsonPath('items.0.subtotal', 200000);
    }

    public function test_invoice_code_format()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create(['nama' => 'Marathon']);
        $session = EventSession::factory()->create(['event_id' => $event->id]);

        $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 1],
            ],
        ]);

        $response = $this->getJson("/api/events/{$event->id}/invoices");

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression('/^MARA-0001$/', $response->json('0.kode'));
    }

    public function test_quota_allows_buying_remaining()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 5]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 5],
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_quota_exceeding_returns_422()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 5]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 6],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_session_belongs_to_different_event_returns_422()
    {
        Sanctum::actingAs($this->user);
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event2->id]);

        $response = $this->postJson("/api/events/{$event1->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_session_in_single_invoice_returns_422()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 1],
                ['event_session_id' => $session->id, 'qty' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_qty_zero_returns_422()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id]);

        $response = $this->postJson("/api/events/{$event->id}/invoices", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_edit_invoice_does_not_double_count_own_items()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 10, 'harga' => 100000]);
        $invoice = Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 2,
        ]);

        $response = $this->putJson("/api/invoices/{$invoice->id}", [
            'nama_peserta' => 'John Updated',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 3],
            ],
        ]);

        $response->assertStatus(200);
        $session->refresh();
        $this->assertEquals(7, $session->kuota - $session->invoiceItems()->sum('qty'));
    }

    public function test_edit_invoice_updates_total()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);
        $invoice = Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->putJson("/api/invoices/{$invoice->id}", [
            'nama_peserta' => 'John',
            'items' => [
                ['event_session_id' => $session->id, 'qty' => 3],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('total', 300000);
    }

    public function test_toggle_status_lunas_sets_paid_at()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'belum_lunas']);

        $response = $this->patchJson("/api/invoices/{$invoice->id}/status", [
            'status' => 'lunas',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_toggle_status_not_lunas_clears_paid_at()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $invoice = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);

        $response = $this->patchJson("/api/invoices/{$invoice->id}/status", [
            'status' => 'belum_lunas',
        ]);

        $response->assertStatus(200);
        $this->assertNull($invoice->fresh()->paid_at);
    }

    public function test_delete_invoice_returns_quota()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 100]);
        $invoice = Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 25,
        ]);

        $response = $this->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(204);
        $this->assertEquals(100, $session->fresh()->kuota);
    }

    public function test_price_change_after_transaction_does_not_affect_old_invoice()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'harga' => 100000]);
        $invoice = Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
        ]);

        $this->putJson("/api/sessions/{$session->id}", [
            'nama' => $session->nama,
            'harga' => 150000,
            'kuota' => $session->kuota,
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('items.0.harga', 100000);
    }

    public function test_filter_status()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $invoice1 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'lunas']);
        $invoice2 = Invoice::factory()->create(['event_id' => $event->id, 'status' => 'belum_lunas']);

        $response = $this->getJson("/api/events/{$event->id}/invoices?status=lunas");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $invoice1->id);
    }

    public function test_search_query()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        Invoice::factory()->create(['event_id' => $event->id, 'nama_peserta' => 'John Doe']);
        Invoice::factory()->create(['event_id' => $event->id, 'nama_peserta' => 'Jane Smith']);

        $response = $this->getJson("/api/events/{$event->id}/invoices?q=John");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_search_query_sql_injection_prevented()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        Invoice::factory()->create(['event_id' => $event->id, 'nama_peserta' => 'John']);
        Invoice::factory()->create(['event_id' => $event->id, 'nama_peserta' => 'Jane']);

        $response = $this->getJson("/api/events/{$event->id}/invoices?q='%20OR%201=1%20--");

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_response_includes_session_name()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'nama' => 'Reguler']);
        $invoice = Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('items.0.session_nama', 'Reguler');
    }

    public function test_get_all_invoices_for_event()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        Invoice::factory()->count(3)->create(['event_id' => $event->id]);

        $response = $this->getJson("/api/events/{$event->id}/invoices");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }
}
