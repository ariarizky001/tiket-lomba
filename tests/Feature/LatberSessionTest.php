<?php

namespace Tests\Feature;

use App\Models\Latber;
use App\Models\LatberSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LatberSessionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $latber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->latber = Latber::create(['nama' => 'Latber Utama', 'tanggal' => '2026-11-15']);
    }

    public function test_create_session_with_default_nomor_urut()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/latbers/{$this->latber->id}/sessions", [
            'nama' => 'Murai Batu A',
            'harga' => 50000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nomor_urut', 1)
            ->assertJsonPath('nama', 'Murai Batu A')
            ->assertJsonPath('harga', 50000)
            ->assertJsonPath('pemasukan', 0);

        $response2 = $this->postJson("/api/latbers/{$this->latber->id}/sessions", [
            'nama' => 'Cucak Hijau',
            'harga' => 75000,
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('nomor_urut', 2);
    }

    public function test_create_session_duplicate_nama_in_same_latber_rejected()
    {
        Sanctum::actingAs($this->user);

        LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000]);

        $response = $this->postJson("/api/latbers/{$this->latber->id}/sessions", [
            'nama' => 'Murai Batu A',
            'harga' => 60000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nama']);
    }

    public function test_create_session_duplicate_nama_in_different_latber_allowed()
    {
        Sanctum::actingAs($this->user);

        LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000]);
        $latber2 = Latber::create(['nama' => 'Latber Kedua', 'tanggal' => '2026-11-16']);

        $response = $this->postJson("/api/latbers/{$latber2->id}/sessions", [
            'nama' => 'Murai Batu A',
            'harga' => 60000,
        ]);

        $response->assertStatus(201);
    }

    public function test_create_session_duplicate_nomor_urut_rejected()
    {
        Sanctum::actingAs($this->user);

        LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000]);

        $response = $this->postJson("/api/latbers/{$this->latber->id}/sessions", [
            'nomor_urut' => 1,
            'nama' => 'Cucak Hijau',
            'harga' => 75000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_urut']);
    }

    public function test_reorder_sessions()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi 1', 'harga' => 50000]);
        $s2 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 2, 'nama' => 'Sesi 2', 'harga' => 75000]);
        $s3 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 3, 'nama' => 'Sesi 3', 'harga' => 100000]);

        $response = $this->putJson("/api/latbers/{$this->latber->id}/sessions/reorder", [
            'ids' => [$s3->id, $s1->id, $s2->id],
        ]);

        $response->assertStatus(200);

        $this->assertEquals(1, $s3->fresh()->nomor_urut);
        $this->assertEquals(2, $s1->fresh()->nomor_urut);
        $this->assertEquals(3, $s2->fresh()->nomor_urut);
    }

    public function test_reorder_validation_rejects_incomplete_or_invalid_ids()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi 1', 'harga' => 50000]);
        $s2 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 2, 'nama' => 'Sesi 2', 'harga' => 75000]);

        // Incomplete
        $this->putJson("/api/latbers/{$this->latber->id}/sessions/reorder", ['ids' => [$s1->id]])
            ->assertStatus(422);

        // Duplicate
        $this->putJson("/api/latbers/{$this->latber->id}/sessions/reorder", ['ids' => [$s1->id, $s1->id]])
            ->assertStatus(422);

        // Belongs to another latber
        $latber2 = Latber::create(['nama' => 'Other', 'tanggal' => '2026-11-16']);
        $otherSession = LatberSession::create(['latber_id' => $latber2->id, 'nomor_urut' => 1, 'nama' => 'Other Sesi', 'harga' => 10000]);

        $this->putJson("/api/latbers/{$this->latber->id}/sessions/reorder", ['ids' => [$s1->id, $otherSession->id]])
            ->assertStatus(422);
    }

    public function test_update_terjual_and_terpesan()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000]);
        $s2 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 2, 'nama' => 'Cucak Hijau', 'harga' => 75000]);

        // Update Terjual
        $response = $this->putJson("/api/latbers/{$this->latber->id}/terjual", [
            'sesi' => [
                ['id' => $s1->id, 'jumlah' => 10],
                ['id' => $s2->id, 'jumlah' => 4],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('0.terjual', 10)
            ->assertJsonPath('0.pemasukan', 500000)
            ->assertJsonPath('1.terjual', 4)
            ->assertJsonPath('1.pemasukan', 300000);

        // Update Terpesan
        $response2 = $this->putJson("/api/latbers/{$this->latber->id}/terpesan", [
            'sesi' => [
                ['id' => $s1->id, 'jumlah' => 5],
                ['id' => $s2->id, 'jumlah' => 2],
            ],
        ]);

        $response2->assertStatus(200)
            ->assertJsonPath('0.terpesan', 5)
            ->assertJsonPath('0.pemasukan', 500000) // Pemasukan unchanged
            ->assertJsonPath('1.terpesan', 2)
            ->assertJsonPath('1.pemasukan', 300000);
    }

    public function test_update_count_validation_fails()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000]);

        // Negative jumlah
        $this->putJson("/api/latbers/{$this->latber->id}/terjual", [
            'sesi' => [['id' => $s1->id, 'jumlah' => -1]],
        ])->assertStatus(422);

        // Exceed max 99999
        $this->putJson("/api/latbers/{$this->latber->id}/terjual", [
            'sesi' => [['id' => $s1->id, 'jumlah' => 100000]],
        ])->assertStatus(422);

        // Session ID belongs to another latber
        $latber2 = Latber::create(['nama' => 'Other', 'tanggal' => '2026-11-16']);
        $sOther = LatberSession::create(['latber_id' => $latber2->id, 'nomor_urut' => 1, 'nama' => 'Other Sesi', 'harga' => 10000]);

        $this->putJson("/api/latbers/{$this->latber->id}/terjual", [
            'sesi' => [['id' => $sOther->id, 'jumlah' => 5]],
        ])->assertStatus(422);

        // Unchanged verification
        $this->assertEquals(0, $s1->fresh()->terjual);
    }

    public function test_update_price_changes_pemasukan()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000, 'terjual' => 10]);

        $this->assertEquals(500000, $s1->pemasukan);

        $response = $this->putJson("/api/latber-sessions/{$s1->id}", [
            'nama' => 'Murai Batu A',
            'harga' => 60000,
            'nomor_urut' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('harga', 60000)
            ->assertJsonPath('pemasukan', 600000);
    }

    public function test_delete_session_with_data_rejected()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Sesi Terjual', 'harga' => 50000, 'terjual' => 1]);
        $s2 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 2, 'nama' => 'Sesi Terpesan', 'harga' => 50000, 'terpesan' => 1]);
        $s3 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 3, 'nama' => 'Sesi Kosong', 'harga' => 50000]);

        $this->deleteJson("/api/latber-sessions/{$s1->id}")->assertStatus(422)
            ->assertJsonPath('message', 'Sesi sudah memiliki data penjualan dan tidak dapat dihapus');

        $this->deleteJson("/api/latber-sessions/{$s2->id}")->assertStatus(422)
            ->assertJsonPath('message', 'Sesi sudah memiliki data penjualan dan tidak dapat dihapus');

        $this->deleteJson("/api/latber-sessions/{$s3->id}")->assertStatus(204);
        $this->assertDatabaseMissing('latber_sessions', ['id' => $s3->id]);
    }

    public function test_ambil_terpesan_success()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000, 'terpesan' => 10]);

        $response = $this->putJson("/api/latbers/{$this->latber->id}/terpesan/ambil", [
            'session_id' => $s1->id,
            'jumlah' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('0.terpesan', 7)
            ->assertJsonPath('0.pemasukan_terpesan', 150000)
            ->assertJsonPath('0.pemasukan_total', 150000);

        $s1->refresh();
        $this->assertEquals(7, $s1->terpesan);
        $this->assertEquals(150000, $s1->pemasukan_terpesan);
    }

    public function test_ambil_terpesan_exceeds_available()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000, 'terpesan' => 5]);

        $response = $this->putJson("/api/latbers/{$this->latber->id}/terpesan/ambil", [
            'session_id' => $s1->id,
            'jumlah' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah melebihi tiket terpesan yang tersedia');

        $s1->refresh();
        $this->assertEquals(5, $s1->terpesan);
        $this->assertEquals(0, $s1->pemasukan_terpesan);
    }

    public function test_ambil_terpesan_wrong_latber_session()
    {
        Sanctum::actingAs($this->user);

        $latber2 = Latber::create(['nama' => 'Latber 2', 'tanggal' => '2026-11-16']);
        $s1 = LatberSession::create(['latber_id' => $latber2->id, 'nomor_urut' => 1, 'nama' => 'Other Session', 'harga' => 50000, 'terpesan' => 10]);

        $response = $this->putJson("/api/latbers/{$this->latber->id}/terpesan/ambil", [
            'session_id' => $s1->id,
            'jumlah' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Sesi tidak ditemukan dalam latber ini');
    }

    public function test_ambil_terpesan_multiple_times()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create(['latber_id' => $this->latber->id, 'nomor_urut' => 1, 'nama' => 'Murai Batu A', 'harga' => 50000, 'terpesan' => 10]);

        $this->putJson("/api/latbers/{$this->latber->id}/terpesan/ambil", [
            'session_id' => $s1->id,
            'jumlah' => 3,
        ])->assertStatus(200);

        $this->putJson("/api/latbers/{$this->latber->id}/terpesan/ambil", [
            'session_id' => $s1->id,
            'jumlah' => 4,
        ])->assertStatus(200);

        $s1->refresh();
        $this->assertEquals(3, $s1->terpesan);
        $this->assertEquals(350000, $s1->pemasukan_terpesan);
    }

    public function test_pemasukan_total_calculation()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create([
            'latber_id' => $this->latber->id, 
            'nomor_urut' => 1, 
            'nama' => 'Murai Batu A', 
            'harga' => 50000, 
            'terjual' => 10,
            'terpesan' => 5,
            'pemasukan_terpesan' => 150000
        ]);

        $this->assertEquals(500000, $s1->pemasukan);
        $this->assertEquals(150000, $s1->pemasukan_terpesan);
        $this->assertEquals(650000, $s1->pemasukan_total);
        $this->assertEquals(250000, $s1->nilai_terpesan);
    }

    public function test_latber_ringkasan_with_pemasukan_terpesan()
    {
        Sanctum::actingAs($this->user);

        $s1 = LatberSession::create([
            'latber_id' => $this->latber->id, 
            'nomor_urut' => 1, 
            'nama' => 'Murai Batu A', 
            'harga' => 50000, 
            'terjual' => 10,
            'terpesan' => 5,
            'pemasukan_terpesan' => 150000
        ]);

        $response = $this->getJson("/api/latbers/{$this->latber->id}");

        $response->assertStatus(200)
            ->assertJsonPath('ringkasan.jumlah_sesi', 1)
            ->assertJsonPath('ringkasan.total_terjual', 10)
            ->assertJsonPath('ringkasan.pemasukan', 650000)
            ->assertJsonPath('ringkasan.total_terpesan', 5)
            ->assertJsonPath('ringkasan.nilai_terpesan', 250000)
            ->assertJsonPath('ringkasan.pemasukan_terpesan', 150000);
    }
}
