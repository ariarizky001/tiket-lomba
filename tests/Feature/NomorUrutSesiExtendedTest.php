<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NomorUrutSesiExtendedTest extends TestCase
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
    public function test_duplicate_nomor_urut_in_same_event_rejected()
    {
        EventSession::factory()->create([
            'event_id' => $this->event->id,
            'nomor_urut' => 1,
            'nama' => 'Sesi A',
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => 'Sesi B',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1, // Duplicate in same event
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nomor_urut');
    }

    /** @test */
    public function test_duplicate_nomor_urut_in_different_event_allowed()
    {
        $event2 = Event::factory()->create();

        EventSession::factory()->create([
            'event_id' => $this->event->id,
            'nomor_urut' => 1,
            'nama' => 'Sesi A',
        ]);

        $response = $this->postJson("/api/events/{$event2->id}/sessions", [
            'nama' => 'Sesi A Event 2',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1, // Duplicate across events is allowed
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nomor_urut', 1);
    }

    /** @test */
    public function test_auto_fill_nomor_urut_on_post()
    {
        // First session without nomor_urut -> 1
        $response1 = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => 'Sesi A',
            'harga' => 100000,
            'kuota' => 10,
        ]);

        $response1->assertStatus(201)
            ->assertJsonPath('nomor_urut', 1);

        // Second session without nomor_urut -> 2
        $response2 = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => 'Sesi B',
            'harga' => 100000,
            'kuota' => 10,
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('nomor_urut', 2);
    }

    /** @test */
    public function test_main_ordering_scenario_sesi_b_created_first_with_nomor_2_then_sesi_a_with_nomor_1()
    {
        // Create Sesi B first with nomor_urut = 2
        $sessionB = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'nomor_urut' => 2,
            'nama' => 'Sesi B',
            'harga' => 200000,
        ]);

        // Create Sesi A second with nomor_urut = 1
        $sessionA = EventSession::factory()->create([
            'event_id' => $this->event->id,
            'nomor_urut' => 1,
            'nama' => 'Sesi A',
            'harga' => 100000,
        ]);

        // 1. Check GET /sessions -> Sesi A must come before Sesi B
        $responseSessions = $this->getJson("/api/events/{$this->event->id}/sessions");
        $responseSessions->assertStatus(200)
            ->assertJsonPath('0.nama', 'Sesi A')
            ->assertJsonPath('1.nama', 'Sesi B');

        // 2. Check GET /report -> Sesi A must come before Sesi B
        $responseReport = $this->getJson("/api/events/{$this->event->id}/report");
        $responseReport->assertStatus(200)
            ->assertJsonPath('sesi.0.nama', 'Sesi A')
            ->assertJsonPath('sesi.1.nama', 'Sesi B');

        // 3. Check GET /rekap-peserta -> Sesi A must come before Sesi B
        $responseRekap = $this->getJson("/api/events/{$this->event->id}/rekap-peserta");
        $responseRekap->assertStatus(200)
            ->assertJsonPath('sesi.0.nama', 'Sesi A')
            ->assertJsonPath('sesi.0.nomor_urut', 1)
            ->assertJsonPath('sesi.1.nama', 'Sesi B')
            ->assertJsonPath('sesi.1.nomor_urut', 2);

        // 4. Create an invoice with both sessions and check items ordering
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $sessionB->id, // Added B first
            'qty' => 1,
            'harga' => 200000,
            'subtotal' => 200000,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $sessionA->id, // Added A second
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        $responseInvoices = $this->getJson("/api/events/{$this->event->id}/invoices");
        $responseInvoices->assertStatus(200)
            ->assertJsonPath('0.items.0.session.nama', 'Sesi A') // A first because nomor_urut=1
            ->assertJsonPath('0.items.1.session.nama', 'Sesi B');

        // 5. Render invoice text -> Sesi A first, then Sesi B
        $renderer = new InvoiceRenderer();
        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'kelas_pesanan' => [
                    'aktif' => true,
                    'teks' => 'KELAS',
                    'format_baris' => '{no}. {nomor_sesi}. {nama_sesi} — {harga} = {qty}',
                ],
            ],
        ];

        $invoice->load(['items.session']);
        $rendered = $renderer->render($templateData, $this->event, $invoice);

        $this->assertStringContainsString('1. 1. Sesi A — Rp100.000 = 1', $rendered);
        $this->assertStringContainsString('2. 2. Sesi B — Rp200.000 = 1', $rendered);
    }

    /** @test */
    public function test_reorder_sessions_endpoint()
    {
        $session1 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 1, 'nama' => 'Sesi A']);
        $session2 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 2, 'nama' => 'Sesi B']);
        $session3 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 3, 'nama' => 'Sesi C']);

        // Swap session1 and session3: order B, C, A
        $response = $this->putJson("/api/events/{$this->event->id}/sessions/reorder", [
            'ids' => [$session2->id, $session3->id, $session1->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('0.id', $session2->id)
            ->assertJsonPath('0.nomor_urut', 1)
            ->assertJsonPath('1.id', $session3->id)
            ->assertJsonPath('1.nomor_urut', 2)
            ->assertJsonPath('2.id', $session1->id)
            ->assertJsonPath('2.nomor_urut', 3);

        // Reject incomplete IDs
        $responseIncomplete = $this->putJson("/api/events/{$this->event->id}/sessions/reorder", [
            'ids' => [$session1->id, $session2->id], // Missing session3
        ]);
        $responseIncomplete->assertStatus(422);

        // Reject duplicate IDs
        $responseDuplicate = $this->putJson("/api/events/{$this->event->id}/sessions/reorder", [
            'ids' => [$session1->id, $session1->id, $session2->id],
        ]);
        $responseDuplicate->assertStatus(422);

        // Reject IDs from another event
        $event2 = Event::factory()->create();
        $sessionOther = EventSession::factory()->create(['event_id' => $event2->id]);
        $responseOther = $this->putJson("/api/events/{$this->event->id}/sessions/reorder", [
            'ids' => [$session1->id, $session2->id, $sessionOther->id],
        ]);
        $responseOther->assertStatus(422);
    }

    /** @test */
    public function test_delete_session_does_not_renumber_others()
    {
        $session1 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 1]);
        $session2 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 2]);
        $session3 = EventSession::factory()->create(['event_id' => $this->event->id, 'nomor_urut' => 3]);

        // Delete middle session (session2)
        $response = $this->deleteJson("/api/sessions/{$session2->id}");
        $response->assertStatus(204);

        // Verify remaining sessions keep their nomor_urut (1 and 3)
        $this->assertEquals(1, $session1->fresh()->nomor_urut);
        $this->assertEquals(3, $session3->fresh()->nomor_urut);
    }
}