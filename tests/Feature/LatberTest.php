<?php

namespace Tests\Feature;

use App\Models\Latber;
use App\Models\LatberSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LatberTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantLatberPermissions();
    }

    protected function grantLatberPermissions(): void
    {
        $permissionNames = [
            'view_latbers',
            'manage_latbers',
            'view_latber_sessions',
            'manage_latber_sessions',
        ];

        foreach ($permissionNames as $name) {
            $permission = \App\Models\Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst(str_replace('_', ' ', $name)), 'category' => 'latber', 'description' => 'Test permission']
            );
            
            \App\Models\UserPermission::firstOrCreate([
                'user_id' => $this->user->id,
                'permission_id' => $permission->id,
            ], [
                'granted' => true,
            ]);
        }
    }

    public function test_unauthenticated_requests_return_401()
    {
        $this->getJson('/api/latbers')->assertStatus(401);
        $this->postJson('/api/latbers', ['nama' => 'Test', 'tanggal' => '2026-11-15'])->assertStatus(401);
    }

    public function test_create_latber_with_valid_data()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/latbers', [
            'nama' => 'Latber Minggu',
            'tanggal' => '2026-11-15',
            'lokasi' => 'Gantangan Utama',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nama', 'Latber Minggu')
            ->assertJsonPath('lokasi', 'Gantangan Utama')
            ->assertJsonStructure([
                'id', 'nama', 'tanggal', 'lokasi',
                'ringkasan' => ['jumlah_sesi', 'total_terjual', 'pemasukan', 'total_terpesan']
            ]);
    }

    public function test_create_latber_validation_fails()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/latbers', [
            'tanggal' => 'invalid-date',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'tanggal']);
    }

    public function test_get_latbers_sorted_by_tanggal_desc()
    {
        Sanctum::actingAs($this->user);

        $latber1 = Latber::create(['nama' => 'Latber 1', 'tanggal' => '2026-11-10']);
        $latber2 = Latber::create(['nama' => 'Latber 2', 'tanggal' => '2026-11-20']);
        $latber3 = Latber::create(['nama' => 'Latber 3', 'tanggal' => '2026-11-15']);

        $response = $this->getJson('/api/latbers');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals($latber2->id, $data[0]['id']);
        $this->assertEquals($latber3->id, $data[1]['id']);
        $this->assertEquals($latber1->id, $data[2]['id']);
    }

    public function test_show_latber_includes_ringkasan()
    {
        Sanctum::actingAs($this->user);

        $latber = Latber::create(['nama' => 'Latber Test', 'tanggal' => '2026-11-15']);
        LatberSession::create(['latber_id' => $latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi 1', 'harga' => 50000, 'terjual' => 10, 'terpesan' => 5]);
        LatberSession::create(['latber_id' => $latber->id, 'nomor_urut' => 2, 'nama' => 'Sesi 2', 'harga' => 75000, 'terjual' => 4, 'terpesan' => 2]);

        $response = $this->getJson("/api/latbers/{$latber->id}");

        $response->assertStatus(200)
            ->assertJsonPath('ringkasan.jumlah_sesi', 2)
            ->assertJsonPath('ringkasan.total_terjual', 14)
            ->assertJsonPath('ringkasan.pemasukan', 800000)
            ->assertJsonPath('ringkasan.total_terpesan', 7);
    }

    public function test_update_latber()
    {
        Sanctum::actingAs($this->user);

        $latber = Latber::create(['nama' => 'Old Name', 'tanggal' => '2026-11-15']);

        $response = $this->putJson("/api/latbers/{$latber->id}", [
            'nama' => 'Updated Name',
            'tanggal' => '2026-11-16',
            'lokasi' => 'New Location',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('nama', 'Updated Name')
            ->assertJsonPath('lokasi', 'New Location');
    }

    public function test_delete_empty_latber()
    {
        Sanctum::actingAs($this->user);

        $latber = Latber::create(['nama' => 'Latber Empty', 'tanggal' => '2026-11-15']);
        $session = LatberSession::create(['latber_id' => $latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi Empty', 'harga' => 50000, 'terjual' => 0, 'terpesan' => 0]);

        $response = $this->deleteJson("/api/latbers/{$latber->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('latbers', ['id' => $latber->id]);
        $this->assertDatabaseMissing('latber_sessions', ['id' => $session->id]);
    }

    public function test_delete_latber_with_sales_rejected()
    {
        Sanctum::actingAs($this->user);

        $latber = Latber::create(['nama' => 'Latber Sales', 'tanggal' => '2026-11-15']);
        LatberSession::create(['latber_id' => $latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi Sales', 'harga' => 50000, 'terjual' => 1, 'terpesan' => 0]);

        $response = $this->deleteJson("/api/latbers/{$latber->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Latber sudah memiliki data penjualan dan tidak dapat dihapus');
        $this->assertDatabaseHas('latbers', ['id' => $latber->id]);
    }
}
