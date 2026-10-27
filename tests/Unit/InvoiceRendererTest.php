<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRendererTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new InvoiceRenderer();
    }

    /** @test */
    public function it_renders_exact_lampiran_a_output()
    {
        $event = Event::create([
            'nama' => '1st Anniversary Solo High Class',
            'tanggal' => '2026-10-11',
            'lokasi' => 'Solo High Class Stadium',
            'is_active' => true,
        ]);

        $session1 = EventSession::create([
            'event_id' => $event->id,
            'nama' => 'MB A PAKET ANNIVERSARY',
            'harga' => 4500000,
            'kuota' => 10,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 277,
            'kode' => 'SHC-0277',
            'nama_peserta' => 'Rizal Medan',
            'total' => 4500000,
            'status' => 'belum_lunas',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session1->id,
            'qty' => 1,
            'harga' => 4500000,
            'subtotal' => 4500000,
        ]);

        $invoice->load(['items.session']);

        $templateData = [
            'pemisah' => '━━━━━━━━━━━━━━━━━━',
            'bagian' => [
                'salam_pembuka' => [
                    'aktif' => true,
                    'teks' => "Assalamu’alaikum Wr. Wb.\nShalom | Salam Sejahtera\n\n🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟\n\nDengan hormat, kami sampaikan Invoice Pemesanan Tiket Event.",
                ],
                'nama_event' => [
                    'aktif' => true,
                    'teks' => "🎪 *{nama_event_besar}*\n📅 {tanggal_event_besar}\n⏰ START NAIK PUKUL 10.00 WIB",
                ],
                'pemesan' => [
                    'aktif' => true,
                    'teks' => "👤 Nama     : {nama_pemesan}\n🔢 No. Urut : {nomor_urut}",
                ],
                'kelas_pesanan' => [
                    'aktif' => true,
                    'teks' => "🎟️ KELAS PESANAN",
                    'format_baris' => "{no}. {nama_sesi} — {harga} = {qty}",
                ],
                'total_pembayaran' => [
                    'aktif' => true,
                    'teks' => "💰 TOTAL PEMBAYARAN\n👉 Rp {total_angka}",
                ],
                'ketentuan_pembayaran' => [
                    'aktif' => true,
                    'teks' => "⚠️ KETENTUAN PEMBAYARAN\nPembayaran maksimal 1 × 24 jam setelah invoice diterima.\n\n💳 TRANSFER PEMBAYARAN\n🏦 BCA — 1234567890\n👤 A/N Nama Pemilik\n📲 Konfirmasi: 0812-0000-0000",
                ],
                'penutup' => [
                    'aktif' => true,
                    'teks' => "🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA\n\nMaturnuwun & Salam Nyawiji. ❤️",
                ],
                'iklan' => [
                    'aktif' => true,
                    'teks' => "📍 LOKASI GANTANGAN\nhttps://contoh.example/lokasi\n\n👥 GABUNG KOMUNITAS\nhttps://contoh.example/komunitas",
                ],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        $expected = "Assalamu’alaikum Wr. Wb.\n" .
            "Shalom | Salam Sejahtera\n\n" .
            "🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟\n\n" .
            "Dengan hormat, kami sampaikan Invoice Pemesanan Tiket Event.\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "🎪 *1ST ANNIVERSARY SOLO HIGH CLASS*\n" .
            "📅 MINGGU, 11 OKTOBER 2026\n" .
            "⏰ START NAIK PUKUL 10.00 WIB\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "👤 Nama     : Rizal Medan\n" .
            "🔢 No. Urut : 277\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "🎟️ KELAS PESANAN\n" .
            "1. MB A PAKET ANNIVERSARY — Rp4.500.000 = 1\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "💰 TOTAL PEMBAYARAN\n" .
            "👉 Rp 4.500.000\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "⚠️ KETENTUAN PEMBAYARAN\n" .
            "Pembayaran maksimal 1 × 24 jam setelah invoice diterima.\n\n" .
            "💳 TRANSFER PEMBAYARAN\n" .
            "🏦 BCA — 1234567890\n" .
            "👤 A/N Nama Pemilik\n" .
            "📲 Konfirmasi: 0812-0000-0000\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA\n\n" .
            "Maturnuwun & Salam Nyawiji. ❤️\n" .
            "━━━━━━━━━━━━━━━━━━\n" .
            "📍 LOKASI GANTANGAN\n" .
            "https://contoh.example/lokasi\n\n" .
            "👥 GABUNG KOMUNITAS\n" .
            "https://contoh.example/komunitas";

        $this->assertEquals($expected, $rendered);
    }

    /** @test */
    public function it_does_not_reprocess_injected_values_single_pass()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => '{total}', // Trap for double replacement!
            'total' => 100000,
            'status' => 'lunas',
        ]);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'pemesan' => [
                    'aktif' => true,
                    'teks' => "Nama: {nama_pemesan}\nTotal: {total}",
                ],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        $this->assertEquals("Nama: {total}\nTotal: Rp100.000", $rendered);
    }

    /** @test */
    public function it_skips_inactive_or_empty_sections_without_double_separator()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test User',
            'total' => 100000,
            'status' => 'lunas',
        ]);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'nama_event' => ['aktif' => false, 'teks' => 'Hidden'],
                'pemesan' => ['aktif' => true, 'teks' => 'Pemesan: {nama_pemesan}'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'Kelas', 'format_baris' => '{no}. {nama_sesi}'],
                'total_pembayaran' => ['aktif' => false, 'teks' => ''],
                'ketentuan_pembayaran' => ['aktif' => true, 'teks' => 'Ketentuan'],
                'penutup' => ['aktif' => false, 'teks' => ''],
                'iklan' => ['aktif' => false, 'teks' => ''],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        // Should have 3 active sections => 2 separators
        $separatorCount = substr_count($rendered, '---');
        $this->assertEquals(2, $separatorCount);
        
        $this->assertStringContainsString('Salam', $rendered);
        $this->assertStringContainsString('Pemesan: Test User', $rendered);
        $this->assertStringContainsString('Ketentuan', $rendered);
        $this->assertStringNotContainsString('Hidden', $rendered);
    }

    /** @test */
    public function it_renders_multiple_sessions_in_order_by_event_session_id()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $session1 = EventSession::create([
            'event_id' => $event->id,
            'nama' => 'Session B',
            'harga' => 200000,
            'kuota' => 10,
        ]);

        $session2 = EventSession::create([
            'event_id' => $event->id,
            'nama' => 'Session A',
            'harga' => 100000,
            'kuota' => 10,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test User',
            'total' => 300000,
            'status' => 'lunas',
        ]);

        // Add session1 first (id 1), then session2 (id 2)
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session1->id,
            'qty' => 1,
            'harga' => 200000,
            'subtotal' => 200000,
        ]);
        
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session2->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);

        $invoice->load(['items.session']);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'kelas_pesanan' => [
                    'aktif' => true,
                    'teks' => 'KELAS',
                    'format_baris' => '{no}. {nama_sesi} — {harga} = {qty}',
                ],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        // Should be ordered by event_session_id ASC (session1 first, then session2)
        $this->assertStringContainsString('1. Session B — Rp200.000 = 1', $rendered);
        $this->assertStringContainsString('2. Session A — Rp100.000 = 1', $rendered);
    }

    /** @test */
    public function it_handles_empty_kelas_pesanan_section()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test User',
            'total' => 0,
            'status' => 'lunas',
        ]);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => 'Salam'],
                'kelas_pesanan' => ['aktif' => true, 'teks' => 'KELAS', 'format_baris' => '{no}. {nama_sesi}'],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        // Only salam_pembuka should appear since kelas_pesanan has no items
        $this->assertEquals('Salam', $rendered);
    }

    /** @test */
    public function it_preserves_emoji_and_unicode_in_database()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test 🌟',
            'total' => 100000,
            'status' => 'lunas',
        ]);

        $templateData = [
            'pemisah' => '━━━',
            'bagian' => [
                'salam_pembuka' => ['aktif' => true, 'teks' => '🌟🌟🌟 Salam 🌟🌟🌟'],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        $this->assertEquals('🌟🌟🌟 Salam 🌟🌟🌟', $rendered);
    }

    /** @test */
    public function it_handles_placeholder_not_known_leaves_as_is()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test',
            'total' => 100000,
            'status' => 'lunas',
        ]);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'pemesan' => [
                    'aktif' => true,
                    'teks' => 'Known: {nama_pemesan} | Unknown: {unknown_placeholder}',
                ],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoice);

        $this->assertEquals('Known: Test | Unknown: {unknown_placeholder}', $rendered);
    }

    /** @test */
    public function it_renders_status_and_status_ikon_placeholders()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $invoiceBelumLunas = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
            'nama_peserta' => 'Test User',
            'total' => 100000,
            'status' => 'belum_lunas',
        ]);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'total_pembayaran' => [
                    'aktif' => true,
                    'teks' => "💰 TOTAL\n📌 STATUS : {status_ikon} {status}",
                ],
            ],
        ];

        $rendered = $this->renderer->render($templateData, $event, $invoiceBelumLunas);
        $this->assertStringContainsString('📌 STATUS : ⏳ BELUM LUNAS', $rendered);
        $this->assertStringNotContainsString('✅', $rendered);

        $invoiceLunas = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 2,
            'kode' => 'EVT-0002',
            'nama_peserta' => 'Test User 2',
            'total' => 200000,
            'status' => 'lunas',
        ]);

        $renderedLunas = $this->renderer->render($templateData, $event, $invoiceLunas);
        $this->assertStringContainsString('📌 STATUS : ✅ LUNAS', $renderedLunas);
        $this->assertStringNotContainsString('⏳', $renderedLunas);
    }

    /** @test */
    public function it_renders_same_invoice_before_and_after_lunas_with_only_status_different()
    {
        $event = Event::create([
            'nama' => 'Event Testing',
            'tanggal' => '2026-10-11',
            'is_active' => true,
        ]);

        $session = EventSession::create([
            'event_id' => $event->id,
            'nama' => 'Session A',
            'harga' => 100000,
            'kuota' => 10,
        ]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'nomor' => 1,
            'kode' => 'EVT-0001',
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

        $invoice->load(['items.session']);

        $templateData = [
            'pemisah' => '---',
            'bagian' => [
                'pemesan' => ['aktif' => true, 'teks' => 'Nama: {nama_pemesan}'],
                'total_pembayaran' => ['aktif' => true, 'teks' => "Total: {total}\nStatus: {status_ikon} {status}"],
            ],
        ];

        $renderedBelumLunas = $this->renderer->render($templateData, $event, $invoice);

        $invoice->update(['status' => 'lunas']);
        $invoice->refresh();

        $renderedLunas = $this->renderer->render($templateData, $event, $invoice);

        $this->assertStringContainsString('Status: ⏳ BELUM LUNAS', $renderedBelumLunas);
        $this->assertStringContainsString('Status: ✅ LUNAS', $renderedLunas);

        $this->assertStringContainsString('Nama: Test User', $renderedBelumLunas);
        $this->assertStringContainsString('Nama: Test User', $renderedLunas);

        $this->assertStringContainsString('Total: Rp100.000', $renderedBelumLunas);
        $this->assertStringContainsString('Total: Rp100.000', $renderedLunas);
    }
}
