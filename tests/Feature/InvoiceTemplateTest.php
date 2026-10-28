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

    private function getValidPayload(): array
    {
        return [
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian_belum_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
            'bagian_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => false, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];
    }

    /** @test */
    public function get_template_returns_default_when_not_set()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/events/{$this->event->id}/invoice-template");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pemisah',
                'bagian_belum_lunas',
                'bagian_lunas',
                'is_default',
                'placeholder' => ['umum', 'baris'],
            ])
            ->assertJsonPath('is_default', true);
    }

    /** @test */
    public function put_template_saves_and_returns_with_is_default_false()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();

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
            'bagian_belum_lunas' => config('invoice_template.bagian_belum_lunas'),
            'bagian_lunas' => config('invoice_template.bagian_lunas'),
        ]);

        $payload = $this->getValidPayload();
        $payload['pemisah'] = '===';
        $payload['bagian_belum_lunas']['salam_pembuka']['teks'] = 'Updated';
        $payload['bagian_lunas']['salam_pembuka']['teks'] = 'Updated';

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
            'bagian_belum_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
            ],
            'bagian_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian_belum_lunas');
    }

    /** @test */
    public function put_template_validation_rejects_extra_bagian_keys()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();
        $payload['bagian_belum_lunas']['extra_key'] = ['aktif' => true, 'teks' => 'Extra'];
        $payload['bagian_lunas']['extra_key'] = ['aktif' => true, 'teks' => 'Extra'];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian_belum_lunas');
    }

    /** @test */
    public function put_template_validation_rejects_non_boolean_aktif()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();
        $payload['bagian_belum_lunas']['salam_pembuka']['aktif'] = 'true';
        $payload['bagian_lunas']['salam_pembuka']['aktif'] = 'true';

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian_belum_lunas.salam_pembuka.aktif');
    }

    /** @test */
    public function put_template_validation_rejects_teks_over_2000_chars()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();
        $payload['bagian_belum_lunas']['salam_pembuka']['teks'] = str_repeat('a', 2001);
        $payload['bagian_lunas']['salam_pembuka']['teks'] = str_repeat('a', 2001);

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian_belum_lunas.salam_pembuka.teks');
    }

    /** @test */
    public function put_template_validation_rejects_missing_format_baris()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();
        $payload['bagian_belum_lunas']['kelas_pesanan']['format_baris'] = '';
        $payload['bagian_lunas']['kelas_pesanan']['format_baris'] = '';

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('bagian_belum_lunas.kelas_pesanan.format_baris');
    }

    /** @test */
    public function preview_does_not_save_to_database()
    {
        Sanctum::actingAs($this->user);

        $payload = $this->getValidPayload();

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
            'nomor_urut' => 1,
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
            'bagian_belum_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => '{nama_event}'],
                'pemesan' => ['aktif' => true, 'teks' => '{nama_pemesan}'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total: {total}'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
            'bagian_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => true, 'teks' => '{nama_event}'],
                'pemesan' => ['aktif' => true, 'teks' => '{nama_pemesan}'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total: {total}'],
                'ketentuan_pembayaran' => ['aktif' => false, 'teks' => 'Ketentuan'],
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
            'nomor_urut' => 1,
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

        $payload = $this->getValidPayload();
        $payload['bagian_belum_lunas']['salam_pembuka']['teks'] = '🌟🌟🌟 Salam 🌟🌟🌟';
        $payload['bagian_lunas']['salam_pembuka']['teks'] = '🌟🌟🌟 Salam 🌟🌟🌟';

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(200);

        $stored = InvoiceTemplate::where('event_id', $this->event->id)->first();
        $this->assertStringContainsString('🌟', $stored->bagian_belum_lunas['salam_pembuka']['teks']);
        $this->assertStringContainsString('🌟', $stored->bagian_lunas['salam_pembuka']['teks']);
    }

    /** @test */
    public function get_invoice_teks_has_cache_control_no_store_header()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'Test Session',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1,
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

        $response->assertStatus(200);
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    /** @test */
    public function status_changes_from_belum_lunas_to_lunas_and_back()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'Test Session',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1,
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

        InvoiceTemplate::create([
            'event_id' => $this->event->id,
            'pemisah' => '---',
            'bagian_belum_lunas' => [
                'total_pembayaran' => ['aktif' => true, 'teks' => "Status: {status_ikon} {status}"],
            ],
            'bagian_lunas' => [
                'total_pembayaran' => ['aktif' => true, 'teks' => "Status: {status_ikon} {status}"],
            ],
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}/teks");
        $response->assertStatus(200);
        $this->assertStringContainsString('Status: ⏳ BELUM LUNAS', $response['teks']);

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'lunas']);

        $response = $this->getJson("/api/invoices/{$invoice->id}/teks");
        $response->assertStatus(200);
        $this->assertStringContainsString('Status: ✅ LUNAS', $response['teks']);

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'belum_lunas']);

        $response = $this->getJson("/api/invoices/{$invoice->id}/teks");
        $response->assertStatus(200);
        $this->assertStringContainsString('Status: ⏳ BELUM LUNAS', $response['teks']);
    }

    /** @test */
    public function default_template_contains_status_placeholder()
    {
        $defaultTemplate = config('invoice_template');
        
        $this->assertArrayHasKey('total_pembayaran', $defaultTemplate['bagian_belum_lunas']);
        $this->assertStringContainsString('{status}', $defaultTemplate['bagian_belum_lunas']['total_pembayaran']['teks']);
        $this->assertStringContainsString('{status_ikon}', $defaultTemplate['bagian_belum_lunas']['total_pembayaran']['teks']);
    }

    /** @test */
    public function put_template_saves_both_belum_lunas_and_lunas()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian_belum_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam Belum Lunas'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
            'bagian_lunas' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam Lunas'],
                'nama_event' => ['aktif' => true, 'teks' => 'Event'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => 'Total'],
                'ketentuan_pembayaran' => ['aktif' => false, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => true, 'teks' => 'Penutup'],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $response = $this->putJson("/api/events/{$this->event->id}/invoice-template", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('bagian_belum_lunas.salam_pembuka.teks', 'Salam Belum Lunas')
            ->assertJsonPath('bagian_lunas.salam_pembuka.teks', 'Salam Lunas')
            ->assertJsonPath('bagian_lunas.ketentuan_pembayaran.aktif', false);

        $stored = InvoiceTemplate::where('event_id', $this->event->id)->first();
        $this->assertEquals('Salam Belum Lunas', $stored->bagian_belum_lunas['salam_pembuka']['teks']);
        $this->assertEquals('Salam Lunas', $stored->bagian_lunas['salam_pembuka']['teks']);
        $this->assertFalse($stored->bagian_lunas['ketentuan_pembayaran']['aktif']);
    }

    /** @test */
    public function preview_with_status_belum_lunas_and_lunas()
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'pemisah' => '---',
            'bagian_belum_lunas' => [
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'KETENTUAN BELUM LUNAS'],
            ],
            'bagian_lunas' => [
                'ketentuan_pembayaran' => ['aktif' => false, 'teks' => 'KETENTUAN LUNAS'],
            ],
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/invoice-template/preview", array_merge($payload, ['preview_status' => 'belum_lunas']));
        $response->assertStatus(200);
        $this->assertStringContainsString('KETENTUAN BELUM LUNAS', $response['teks']);

        $response = $this->postJson("/api/events/{$this->event->id}/invoice-template/preview", array_merge($payload, ['preview_status' => 'lunas']));
        $response->assertStatus(200);
        $this->assertStringNotContainsString('KETENTUAN LUNAS', $response['teks']);
    }

    /** @test */
    public function auto_migrate_existing_template_on_show()
    {
        Sanctum::actingAs($this->user);

        $invoiceTemplate = InvoiceTemplate::create([
            'event_id' => $this->event->id,
            'pemisah' => '---',
            'bagian' => [
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'KETENTUAN'],
            ],
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/invoice-template");
        $response->assertStatus(200);

        $this->assertArrayHasKey('bagian_belum_lunas', $response->json());
        $this->assertArrayHasKey('bagian_lunas', $response->json());

        $stored = InvoiceTemplate::find($invoiceTemplate->id);
        $this->assertArrayHasKey('ketentuan_pembayaran', $stored->bagian_belum_lunas);
        $this->assertArrayHasKey('ketentuan_pembayaran', $stored->bagian_lunas);
        $this->assertFalse($stored->bagian_lunas['ketentuan_pembayaran']['aktif']);
    }

    /** @test */
    public function get_invoice_teks_renders_different_text_for_different_status()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'Test Session',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1,
        ]);

        $invoiceBelumLunas = Invoice::create([
            'event_id' => $this->event->id,
            'nomor' => 1,
            'kode' => 'SHC-0001',
            'nama_peserta' => 'Test User',
            'total' => 100000,
            'status' => 'belum_lunas',
        ]);

        $invoiceLunas = Invoice::create([
            'event_id' => $this->event->id,
            'nomor' => 2,
            'kode' => 'SHC-0002',
            'nama_peserta' => 'Test User',
            'total' => 100000,
            'status' => 'lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoiceBelumLunas->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoiceLunas->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        InvoiceTemplate::create([
            'event_id' => $this->event->id,
            'pemisah' => '---',
            'bagian_belum_lunas' => [
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'KETENTUAN BELUM LUNAS'],
            ],
            'bagian_lunas' => [
                'ketentuan_pembayaran' => ['aktif' => false, 'teks' => 'KETENTUAN LUNAS'],
            ],
        ]);

        $response = $this->getJson("/api/invoices/{$invoiceBelumLunas->id}/teks");
        $response->assertStatus(200);
        $this->assertStringContainsString('KETENTUAN BELUM LUNAS', $response['teks']);

        $response = $this->getJson("/api/invoices/{$invoiceLunas->id}/teks");
        $response->assertStatus(200);
        $this->assertStringNotContainsString('KETENTUAN LUNAS', $response['teks']);
    }

    /** @test */
    public function no_new_invoice_record_after_status_change()
    {
        Sanctum::actingAs($this->user);

        $session = EventSession::create([
            'event_id' => $this->event->id,
            'nama' => 'Test Session',
            'harga' => 100000,
            'kuota' => 10,
            'nomor_urut' => 1,
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

        $countBefore = Invoice::count();

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'lunas']);

        $countAfter = Invoice::count();

        $this->assertEquals($countBefore, $countAfter);
    }
}
