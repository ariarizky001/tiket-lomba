<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::create([
            'nama' => '1st Anniversary Solo High Class',
            'tanggal' => '2026-10-11',
            'lokasi' => 'Solo High Class Stadium',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function get_template_returns_default_when_not_set()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/events/{$this->event->id}/invoice-template");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pemisah',
                'bagian',
                'is_default',
                'placeholder' => ['umum', 'baris'],
            ])
            ->assertJsonPath('is_default', true);
    }

    /** @test */
    public function put_template_saves_and_returns_with_is_default_false()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('is_default', false)
            ->assertJsonPath('pemisah', '━━━━━━━━━━━━━━━━━━');

        $this->assertDatabaseHas('invoice_templates', [
            'event_id' => $this->event->id,
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
        ]);
    }

    /** @test */
    public function put_template_updates_existing_template()
    {
        Sanctum::actingAs($this->user);

        InvoiceTemplate::create([
            'event_id' => $this->event->id,
            'pemisah' => '---',
            'bagian' => config('invoice_template.bagian'),
        ]);

        $payload = [
            'pemisah' => '===',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Updated'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('pemisah', '===');

        $this->assertDatabaseCount('invoice_templates', 1);
    }

    /** @test */
    public function put_template_validation_rejects_missing_bagian_keys()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian');
    }

    /** @test */
    public function put_template_validation_rejects_extra_bagian_keys()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
                'extra_key' => ['aktif' => true, 'teks' => 'Extra'],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian');
    }

    /** @test */
    public function put_template_validation_rejects_non_boolean_aktif()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => 'true', 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian.salam_pembuka.aktif');
    }

    /** @test */
    public function put_template_validation_rejects_teks_over_2000_chars()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => str_repeat('a', 2001)],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian.salam_pembuka.teks');
    }

    /** @test */
    public function put_template_validation_rejects_missing_format_baris()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian.kelas_pesanan.format_baris');
    }

    /** @test */
    public function preview_does_not_save_to_database()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam Preview'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/invoice-template/preview", $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['teks']);

        $this->assertDatabaseCount('invoice_templates', 0);
    }

    /** @test */
    public function get_invoice_teks_renders_from_template()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'MB A PAKET ANNIVERSARY',
            'harga' => 4500000,
            'kuota' => 10,
        ]);

        $invoice = Invoice::create([
            'event_id' => $this->event->id,
            'nomor' => 277,
            'kode' => 'SHC-0277',
            'nama_peserta' => 'Rizal Medan',
            'total' => 4500000,
            'status' => 'belum_lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 4500000,
            'subtotal' => 4500000,
        ]);

        InvoiceTemplate::create([
            'event_id' => $this->event->id,
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => '{nama_event}'],
                'pemesan' => ['aktif' => true, 'teks' => '{nama_pemesan}'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total: {total}'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}/teks");

        $response->assertStatus(200)
            ->assertJsonStructure(['teks'])
            ->assertJsonPath('teks', function ($teks) {
                return str_contains($teks, 'Salam') &&
                       str_contains($teks, '1st Anniversary Solo High Class') &&
                       str_contains($teks, 'Rizal Medan') &&
                       str_contains($teks, 'Rp4.500.000');
            });
    }

    /** @test */
    public function get_invoice_teks_uses_default_template_if_not_set()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'Test Session',
            'harga' => 100000,
            'kuota' => 10,
        ]);

        $invoice = Invoice::create([
            'event_id' => $this->event->id,
            'nomor' => 1,
            'kode' => 'SHC-0001',
            'nama_peserta' => 'Test User',
            'total' => 100000,
            'status' => 'belum_lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}/teks");

        $response->assertStatus(200)
            ->assertJsonStructure(['teks']);

        $this->assertStringContainsString('Test User', $response['teks']);
    }

    /** @test */
    public function endpoint_without_auth_returns_401()
    {
        $response = $this->getJson("/api/events/{$this->event->id}/invoice-template");

        $response->assertStatus(401);
    }

    /** @test */
    public function put_template_with_emoji_preserves_emoji()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => '🌟🌟🌟 Salam 🌟🌟🌟'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(200);

        $stored = InvoiceTemplate::where('event_id', $this->event->id)->first();
        $this->assertStringContainsString('🌟', $stored->bagian['salam_pembuka']['teks']);
    }
}
