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

class EventSessionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_session_with_valid_data()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();

        $response = $this->postJson("/api/events/{$event->id}/sessions", [
            'nama' => 'Reguler',
            'harga' => 250000,
            'kuota' => 100,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nama', 'Reguler');
    }

    public function test_create_session_validation_fails_with_duplicate_name_in_same_event()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();

        EventSession::factory()->create(['event_id' => $event->id, 'nama' => 'Reguler']);

        $response = $this->postJson("/api/events/{$event->id}/sessions", [
            'nama' => 'Reguler',
            'harga' => 250000,
            'kuota' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_session_allows_duplicate_name_in_different_event()
    {
        Sanctum::actingAs($this->user);
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        EventSession::factory()->create(['event_id' => $event1->id, 'nama' => 'Reguler']);

        $response = $this->postJson("/api/events/{$event2->id}/sessions", [
            'nama' => 'Reguler',
            'harga' => 250000,
            'kuota' => 100,
        ]);

        $response->assertStatus(201);
    }

    public function test_create_session_validation_fails_with_negative_price()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();

        $response = $this->postJson("/api/events/{$event->id}/sessions", [
            'nama' => 'Reguler',
            'harga' => -100000,
            'kuota' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_session_validation_fails_with_invalid_quota()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();

        $response = $this->postJson("/api/events/{$event->id}/sessions", [
            'nama' => 'Reguler',
            'harga' => 250000,
            'kuota' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_get_sessions_returns_terjual_and_sisa_as_integer()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 100]);
        Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $session->event->invoices->first()->id,
            'event_session_id' => $session->id,
            'qty' => 25,
        ]);

        $response = $this->getJson("/api/events/{$event->id}/sessions");

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('0.terjual'));
        $this->assertEquals(75, $response->json('0.sisa'));
    }

    public function test_update_session_does_not_allow_quota_below_terjual()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id, 'kuota' => 100]);
        Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $session->event->invoices->first()->id,
            'event_session_id' => $session->id,
            'qty' => 50,
        ]);

        $response = $this->putJson("/api/sessions/{$session->id}", [
            'nama' => 'Reguler',
            'harga' => 250000,
            'kuota' => 30,
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_session_with_invoices_returns_422()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id]);
        Invoice::factory()->create(['event_id' => $event->id]);
        InvoiceItem::factory()->create([
            'invoice_id' => $session->event->invoices->first()->id,
            'event_session_id' => $session->id,
            'qty' => 1,
        ]);

        $response = $this->deleteJson("/api/sessions/{$session->id}");

        $response->assertStatus(422);
    }

    public function test_delete_session_without_invoices()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id]);

        $response = $this->deleteJson("/api/sessions/{$session->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('event_sessions', ['id' => $session->id]);
    }

    public function test_update_session()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        $session = EventSession::factory()->create(['event_id' => $event->id]);

        $response = $this->putJson("/api/sessions/{$session->id}", [
            'nama' => 'VIP',
            'harga' => 500000,
            'kuota' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('nama', 'VIP');
    }

    public function test_get_all_sessions_for_event()
    {
        Sanctum::actingAs($this->user);
        $event = Event::factory()->create();
        foreach (range(1, 3) as $i) {
            EventSession::factory()->create(['event_id' => $event->id, 'nama' => "Session $i"]);
        }

        $response = $this->getJson("/api/events/{$event->id}/sessions");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }
}
