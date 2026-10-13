<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_create_event_with_valid_data()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'nama' => 'Lomba Maraton 2026',
            'tanggal' => '2026-12-25',
            'lokasi' => 'Jakarta',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nama', 'Lomba Maraton 2026')
            ->assertJsonPath('tanggal', '2026-12-25T00:00:00.000000Z');
    }

    public function test_create_event_validation_fails_without_nama()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'tanggal' => '2026-12-25',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.nama.0', 'The nama field is required.');
    }

    public function test_create_event_validation_fails_without_tanggal()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'nama' => 'Lomba Maraton',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.tanggal.0', 'The tanggal field is required.');
    }

    public function test_get_all_events()
    {
        Sanctum::actingAs($this->user);

        Event::factory()->count(3)->create();

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_get_single_event()
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $event->id);
    }

    public function test_update_event()
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();

        $response = $this->putJson("/api/events/{$event->id}", [
            'nama' => 'Updated Event',
            'tanggal' => '2027-01-01',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('nama', 'Updated Event');
    }

    public function test_delete_event_without_invoices()
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        $sessionId = EventSession::factory()->create(['event_id' => $event->id])->id;

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('event_sessions', ['id' => $sessionId]);
    }

    public function test_delete_event_with_invoices_returns_422()
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Invoice::factory()->create(['event_id' => $event->id]);

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
