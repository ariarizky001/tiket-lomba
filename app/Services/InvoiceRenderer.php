<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceRenderer
{
    /**
     * Re-usable order of template sections.
     */
    public const SECTION_KEYS = [
        'salam_pembuka',
        'nama_event',
        'pemesan',
        'kelas_pesanan',
        'total_pembayaran',
        'ketentuan_pembayaran',
        'penutup',
        'iklan',
    ];

    /**
     * Render template for a given invoice (or mock data).
     *
     * @param array $templateData
     * @param Event $event
     * @param Invoice|array $invoice
     * @return string
     */
    public function render(array $templateData, Event $event, $invoice): string
    {
        $pemisah = $templateData['pemisah'] ?? '━━━━━━━━━━━━━━━━━━';

        // Extract values/items depending on whether $invoice is Model or Array (mock)
        $isModel = $invoice instanceof Invoice;

        $statusVal = $isModel ? $invoice->status : ($invoice['status'] ?? 'belum_lunas');

        // PILIH BAGIAN BERDASARKAN STATUS INVOICE
        if ($statusVal === 'lunas' && isset($templateData['bagian_lunas'])) {
            $bagian = $templateData['bagian_lunas'];
        } elseif ($statusVal === 'belum_lunas' && isset($templateData['bagian_belum_lunas'])) {
            $bagian = $templateData['bagian_belum_lunas'];
        } else {
            // Fallback ke struktur lama (backward compatibility)
            $bagian = $templateData['bagian'] ?? [];
        }

        $namaPeserta = $isModel ? $invoice->nama_peserta : ($invoice['nama_peserta'] ?? '');
        $nomorUrut = $isModel ? (string)(int)$invoice->nomor : (string)(int)($invoice['nomor'] ?? 0);
        $kodeInvoice = $isModel ? $invoice->kode : ($invoice['kode'] ?? '');
        $createdAt = $isModel ? $invoice->created_at : ($invoice['created_at'] ?? now());
        $totalVal = $isModel ? $invoice->total : ($invoice['total'] ?? 0);

        // Indonesian date formatting
        $tanggalEvent = $this->formatIndonesianDate($event->tanggal);
        $tanggalEventBesar = mb_strtoupper($tanggalEvent, 'UTF-8');
        $tanggalInvoice = $this->formatIndonesianDate($createdAt);

        $statusStr = strtoupper($statusVal) === 'LUNAS' ? 'LUNAS' : 'BELUM LUNAS';
        $statusIkon = strtoupper($statusVal) === 'LUNAS' ? '✅' : '⏳';

        // Display total: subtotal + nomor invoice (only for display)
        $displayTotalVal = $totalVal + (int)$nomorUrut;
        $totalAngka = $this->formatRupiahAngka($displayTotalVal);
        $totalRp = 'Rp' . $totalAngka;

        $replacementsGeneral = [
            '{nama_event}' => $event->nama,
            '{nama_event_besar}' => mb_strtoupper($event->nama, 'UTF-8'),
            '{tanggal_event}' => $tanggalEvent,
            '{tanggal_event_besar}' => $tanggalEventBesar,
            '{lokasi_event}' => $event->lokasi ?? '',
            '{nama_pemesan}' => $namaPeserta,
            '{nomor_urut}' => $nomorUrut,
            '{kode_invoice}' => $kodeInvoice,
            '{tanggal_invoice}' => $tanggalInvoice,
            '{total}' => $totalRp,
            '{total_angka}' => $totalAngka,
            '{status}' => $statusStr,
            '{status_ikon}' => $statusIkon,
        ];

        // Process items for kelas_pesanan
        $items = [];
        if ($isModel) {
            // Sort by session nomor_urut ASC, then id ASC
            $itemsCollection = $invoice->items;
            if ($itemsCollection) {
                $items = $itemsCollection->sortBy(function ($item) {
                    return $item->session?->nomor_urut ?? PHP_INT_MAX;
                })->values()->all();
            }
        } else {
            $items = $invoice['items'] ?? [];
            usort($items, function ($a, $b) {
                $aNomor = is_array($a) ? ($a['session']['nomor_urut'] ?? $a['session_nomor'] ?? PHP_INT_MAX) : ($a->session?->nomor_urut ?? PHP_INT_MAX);
                $bNomor = is_array($b) ? ($b['session']['nomor_urut'] ?? $b['session_nomor'] ?? PHP_INT_MAX) : ($b->session?->nomor_urut ?? PHP_INT_MAX);
                return $aNomor <=> $bNomor;
            });
        }

        $renderedSections = [];

        foreach (self::SECTION_KEYS as $key) {
            $sectionConfig = $bagian[$key] ?? null;
            if (!$sectionConfig || empty($sectionConfig['aktif'])) {
                continue;
            }

            $teksRaw = $sectionConfig['teks'] ?? '';

            if ($key === 'kelas_pesanan') {
                if (empty($items)) {
                    continue;
                }

                $formatBaris = $sectionConfig['format_baris'] ?? '{no}. {nama_sesi} — {harga} = {qty}';
                $barisList = [];
                $no = 1;

                foreach ($items as $item) {
                    if (is_array($item)) {
                        $namaSesi = $item['session']['nama'] ?? $item['nama_sesi'] ?? '';
                        $harga = $item['harga'] ?? 0;
                        $qty = $item['qty'] ?? 1;
                        $subtotal = $item['subtotal'] ?? ($harga * $qty);
                    } else {
                        $namaSesi = $item->session ? $item->session->nama : ($item->nama_sesi ?? '');
                        $harga = $item->harga ?? 0;
                        $qty = $item->qty ?? 1;
                        $subtotal = $item->subtotal ?? ($harga * $qty);
                    }

                    $hargaAngka = $this->formatRupiahAngka($harga);
                    $subtotalAngka = $this->formatRupiahAngka($subtotal);

                    $replacementsBaris = [
                        '{no}' => $no++,
                        '{nama_sesi}' => $namaSesi,
                        '{nomor_sesi}' => $item->session?->nomor_urut ?? (is_array($item) ? ($item['session']['nomor_urut'] ?? $item['session_nomor'] ?? '') : ''),
                        '{harga}' => 'Rp' . $hargaAngka,
                        '{harga_angka}' => $hargaAngka,
                        '{qty}' => $qty,
                        '{subtotal}' => 'Rp' . $subtotalAngka,
                        '{subtotal_angka}' => $subtotalAngka,
                    ];

                    $barisRendered = $this->replacePlaceholders($formatBaris, array_merge($replacementsGeneral, $replacementsBaris));
                    $barisList[] = $barisRendered;
                }

                $sectionHeader = $this->replacePlaceholders($teksRaw, $replacementsGeneral);
                
                $fullSectionText = $sectionHeader;
                if (!empty($barisList)) {
                    if (trim($fullSectionText) !== '') {
                        $fullSectionText .= "\n" . implode("\n", $barisList);
                    } else {
                        $fullSectionText = implode("\n", $barisList);
                    }
                }

                $fullSectionText = $this->normalizeLines($fullSectionText);
                if (trim($fullSectionText) !== '') {
                    $renderedSections[] = $fullSectionText;
                }
            } else {
                $renderedText = $this->replacePlaceholders($teksRaw, $replacementsGeneral);
                $renderedText = $this->normalizeLines($renderedText);

                if (trim($renderedText) !== '') {
                    $renderedSections[] = $renderedText;
                }
            }
        }

        return implode("\n" . $pemisah . "\n", $renderedSections);
    }

    /**
     * Single pass regex replacement for placeholders.
     */
    private function replacePlaceholders(string $templateText, array $replacements): string
    {
        return preg_replace_callback('/\{[a-z0-9_]+\}/', function ($matches) use ($replacements) {
            $key = $matches[0];
            return array_key_exists($key, $replacements) ? (string)$replacements[$key] : $key;
        }, $templateText);
    }

    /**
     * Normalize line endings to \n and trim trailing whitespace per line.
     */
    private function normalizeLines(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $lines = array_map(function ($line) {
            return rtrim($line, " \t");
        }, $lines);
        return implode("\n", $lines);
    }

    /**
     * Format number with dot thousands separator (e.g. 4.500.000).
     */
    private function formatRupiahAngka($amount): string
    {
        return number_format((float)$amount, 0, ',', '.');
    }

    /**
     * Format date in Indonesian language (e.g. "Minggu, 11 Oktober 2026").
     */
    private function formatIndonesianDate($date): string
    {
        if (!$date) {
            return '';
        }

        $carbon = Carbon::parse($date)->locale('id');
        
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $dayName = $days[$carbon->format('l')] ?? $carbon->format('l');
        $dayNum = $carbon->format('j');
        $monthName = $months[$carbon->month] ?? $carbon->format('F');
        $year = $carbon->format('Y');

        return "{$dayName}, {$dayNum} {$monthName} {$year}";
    }
}
