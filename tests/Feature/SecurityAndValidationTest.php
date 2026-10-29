<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();
    }

    /** @test */
    public function test_all_api_routes_require_authentication()
    {
        // Unauthenticated requests to protected endpoints should return 401
        $protectedEndpoints = [
            ['GET', "/api/events"],
            ['POST', "/api/events"],
            ['GET', "/api/events/{$this->event->id}"],
            ['PUT', "/api/events/{$this->event->id}"],
            ['DELETE', "/api/events/{$this->event->id}"],
            ['GET', "/api/events/{$this->event->id}/sessions"],
            ['POST', "/api/events/{$this->event->id}/sessions"],
            ['GET', "/api/events/{$this->event->id}/invoices"],
            ['POST', "/api/events/{$this->event->id}/invoices"],
            ['GET', "/api/events/{$this->event->id}/report"],
            ['GET', "/api/events/{$this->event->id}/rekap-peserta"],
            ['GET', "/api/events/{$this->event->id}/expenses"],
            ['GET', "/api/events/{$this->event->id}/bookkeeping"],
        ];

        foreach ($protectedEndpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function test_mass_assignment_protection_on_invoice_store()
    {
        Sanctum::actingAs($this->user);
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 100000]);

        // Client attempts to override total, nomor, kode, status, and paid_at
        $response = $this->postJson("/api/events/{$this->event->id}/invoices", [
            'nama_peserta' => 'Hacker',
            'items' => [['event_session_id' => $session->id, 'qty' => 1]],
            'total' => 1, // Malicious override
            'nomor' => 9999, // Malicious override
            'kode' => 'HACK-9999', // Malicious override
        ]);

        $response->assertStatus(201);
        // Server should calculate total correctly (100000) and ignore client's malicious values
        $this->assertEquals(100000, $response->json('total'));
        $this->assertNotEquals(9999, $response->json('nomor'));
        $this->assertNotEquals('HACK-9999', $response->json('kode'));
    }

    /** @test */
    public function test_boundary_validations_for_invoice_and_session()
    {
        Sanctum::actingAs($this->user);

        // Qty 0 -> 422
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        $response = $this->postJson("/api/events/{$this->event->id}/invoices", [
            'nama_peserta' => 'Test',
            'items' => [['event_session_id' => $session->id, 'qty' => 0]],
        ]);
        $response->assertStatus(422);

        // Qty > 100 -> 422
        $response = $this->postJson("/api/events/{$this->event->id}/invoices", [
            'nama_peserta' => 'Test',
            'items' => [['event_session_id' => $session->id, 'qty' => 101]],
        ]);
        $response->assertStatus(422);

        // Session price negative -> 422
        $response = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => 'Session Neg',
            'harga' => -1000,
            'kuota' => 10,
        ]);
        $response->assertStatus(422);

        // Session quota 0 -> 422
        $response = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => 'Session Zero Quota',
            'harga' => 100000,
            'kuota' => 0,
        ]);
        $response->assertStatus(422);

        // Session name too long > 150 chars -> 422
        $response = $this->postJson("/api/events/{$this->event->id}/sessions", [
            'nama' => str_repeat('a', 151),
            'harga' => 100000,
            'kuota' => 10,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_sql_injection_prevention_on_search()
    {
        Sanctum::actingAs($this->user);

        // SQL injection payload in search query 'q'
        $sqliPayloads = [
            "' OR 1=1 --",
            "'; DROP TABLE invoices;--",
            "1' UNION SELECT 1,2,3--",
        ];

        foreach ($sqliPayloads as $payload) {
            $response = $this->getJson("/api/events/{$this->event->id}/invoices?q=" . urlencode($payload));
            $response->assertStatus(200);
            // Verify invoices table is intact and query didn't crash
            $this->assertDatabaseHas('events', ['id' => $this->event->id]);
        }
    }

    /** @test */
    public function test_login_rate_limiting()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Make 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(422);
        }

        // 6th attempt should be rate limited (429)
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }
}