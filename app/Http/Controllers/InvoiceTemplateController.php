<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceTemplateController extends Controller
{
    private InvoiceRenderer $renderer;

    public function __construct(InvoiceRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    private function getPlaceholders(): array
    {
        return [
            'umum' => [
                '{nama_event}',
                '{nama_event_besar}',
                '{tanggal_event}',
                '{tanggal_event_besar}',
                '{lokasi_event}',
                '{nama_pemesan}',
                '{nomor_urut}',
                '{kode_invoice}',
                '{tanggal_invoice}',
                '{total}',
                '{total_angka}',
                '{status}',
                '{status_ikon}',
            ],
            'baris' => [
                '{no}',
                '{nama_sesi}',
                '{harga}',
                '{harga_angka}',
                '{qty}',
                '{subtotal}',
                '{subtotal_angka}',
            ],
        ];
    }

    public function show(Event $event)
    {
        $template = $event->invoiceTemplate;

        if (!$template) {
            $default = config('invoice_template');
            return response()->json([
                'pemisah' => $default['pemisah'],
                'bagian_belum_lunas' => $default['bagian_belum_lunas'],
                'bagian_lunas' => $default['bagian_lunas'],
                'is_default' => true,
                'placeholder' => $this->getPlaceholders(),
            ]);
        }

        // Auto-migrate jika masih pakai struktur lama
        if ($template->bagian && !$template->bagian_belum_lunas) {
            $bagianLama = $template->bagian;
            
            // Copy ke belum_lunas
            $template->bagian_belum_lunas = $bagianLama;
            
            // Copy ke lunas, tapi matikan ketentuan_pembayaran
            $bagianLunas = $bagianLama;
            if (isset($bagianLunas['ketentuan_pembayaran'])) {
                $bagianLunas['ketentuan_pembayaran']['aktif'] = false;
            }
            $template->bagian_lunas = $bagianLunas;
            
            $template->save();
        }

        return response()->json([
            'pemisah' => $template->pemisah,
            'bagian_belum_lunas' => $template->bagian_belum_lunas,
            'bagian_lunas' => $template->bagian_lunas,
            'is_default' => false,
            'placeholder' => $this->getPlaceholders(),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->validateTemplateRequest($request);

        $template = InvoiceTemplate::updateOrCreate(
            ['event_id' => $event->id],
            [
                'pemisah' => $request->pemisah,
                'bagian_belum_lunas' => $request->bagian_belum_lunas,
                'bagian_lunas' => $request->bagian_lunas,
            ]
        );

        return response()->json([
            'pemisah' => $template->pemisah,
            'bagian_belum_lunas' => $template->bagian_belum_lunas,
            'bagian_lunas' => $template->bagian_lunas,
            'is_default' => false,
            'placeholder' => $this->getPlaceholders(),
        ]);
    }

    public function preview(Request $request, Event $event)
    {
        $this->validatePreviewRequest($request);

        // Fetch first two sessions of event, or mock sessions
        $sessions = $event->sessions()->orderBy('id', 'asc')->take(2)->get();

        $mockItems = [];
        if ($sessions->count() > 0) {
            foreach ($sessions as $session) {
                $mockItems[] = [
                    'event_session_id' => $session->id,
                    'session' => ['nama' => $session->nama],
                    'nama_sesi' => $session->nama,
                    'harga' => $session->harga,
                    'qty' => 1,
                    'subtotal' => $session->harga,
                ];
            }
        } else {
            $mockItems = [
                [
                    'event_session_id' => 1,
                    'nama_sesi' => 'MB A PAKET ANNIVERSARY',
                    'harga' => 4500000,
                    'qty' => 1,
                    'subtotal' => 4500000,
                ],
                [
                    'event_session_id' => 2,
                    'nama_sesi' => 'MB B REGULER',
                    'harga' => 1500000,
                    'qty' => 1,
                    'subtotal' => 1500000,
                ],
            ];
        }

        $totalMock = array_sum(array_column($mockItems, 'subtotal'));

        $previewStatus = $request->preview_status ?? 'belum_lunas';

        $mockInvoice = [
            'nama_peserta' => 'Rizal Medan',
            'nomor' => 277,
            'kode' => 'SHC-0277',
            'created_at' => now(),
            'total' => $totalMock,
            'status' => $previewStatus,
            'items' => $mockItems,
        ];

        $templateData = [
            'pemisah' => $request->pemisah,
            'bagian_belum_lunas' => $request->bagian_belum_lunas,
            'bagian_lunas' => $request->bagian_lunas,
        ];

        $teks = $this->renderer->render($templateData, $event, $mockInvoice);

        return response()->json(['teks' => $teks]);
    }

    public function renderInvoiceText(Invoice $invoice)
    {
        $invoice->load(['items.session', 'event']);
        $event = $invoice->event;

        $template = $event->invoiceTemplate;
        if ($template) {
            $templateData = [
                'pemisah' => $template->pemisah,
                'bagian_belum_lunas' => $template->bagian_belum_lunas,
                'bagian_lunas' => $template->bagian_lunas,
            ];
        } else {
            $templateData = config('invoice_template');
        }

        $teks = $this->renderer->render($templateData, $event, $invoice);

        return response()->json(['teks' => $teks])
            ->header('Cache-Control', 'no-store');
    }

    private function validateTemplateRequest(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'pemisah' => 'required|string|max:50',
            'bagian_belum_lunas' => 'required|array',
            'bagian_lunas' => 'required|array',
        ]);

        $validator->after(function ($v) use ($request) {
            $requiredKeys = InvoiceRenderer::SECTION_KEYS;

            // Validate both bagian_belum_lunas and bagian_lunas
            foreach (['bagian_belum_lunas', 'bagian_lunas'] as $bagianField) {
                $bagian = $request->input($bagianField);
                if (!is_array($bagian)) {
                    $v->errors()->add($bagianField, "Field {$bagianField} harus berupa array.");
                    continue;
                }

                $givenKeys = array_keys($bagian);

                if (count($givenKeys) !== 8 || array_diff($requiredKeys, $givenKeys) !== [] || array_diff($givenKeys, $requiredKeys) !== []) {
                    $v->errors()->add($bagianField, 'Bagian harus berisi tepat 8 kunci standar.');
                    continue;
                }

                foreach ($requiredKeys as $key) {
                    $item = $bagian[$key] ?? null;
                    if (!is_array($item)) {
                        $v->errors()->add("{$bagianField}.{$key}", "Bagian {$key} harus berupa objek.");
                        continue;
                    }

                    if (!array_key_exists('aktif', $item) || !is_bool($item['aktif'])) {
                        $v->errors()->add("{$bagianField}.{$key}.aktif", "Field 'aktif' pada {$key} harus boolean.");
                    }

                    if (array_key_exists('teks', $item) && $item['teks'] !== null) {
                        if (!is_string($item['teks'])) {
                            $v->errors()->add("{$bagianField}.{$key}.teks", "Field 'teks' pada {$key} harus string.");
                        } elseif (mb_strlen($item['teks'], 'UTF-8') > 2000) {
                            $v->errors()->add("{$bagianField}.{$key}.teks", "Field 'teks' pada {$key} maksimal 2000 karakter.");
                        }
                    }

                    if ($key === 'kelas_pesanan') {
                        if (!array_key_exists('format_baris', $item) || !is_string($item['format_baris']) || trim($item['format_baris']) === '') {
                            $v->errors()->add("{$bagianField}.kelas_pesanan.format_baris", "Format baris pada kelas_pesanan wajib diisi.");
                        } elseif (mb_strlen($item['format_baris'], 'UTF-8') > 255) {
                            $v->errors()->add("{$bagianField}.kelas_pesanan.format_baris", "Format baris maksimal 255 karakter.");
                        }
                    }
                }
            }
        });

        $validator->validate();
    }

    private function validatePreviewRequest(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'pemisah' => 'required|string|max:50',
            'bagian_belum_lunas' => 'nullable|array',
            'bagian_lunas' => 'nullable|array',
            'preview_status' => 'sometimes|in:belum_lunas,lunas',
        ]);

        $validator->after(function ($v) use ($request) {
            $requiredKeys = InvoiceRenderer::SECTION_KEYS;

            // Validate both bagian_belum_lunas and bagian_lunas if provided
            foreach (['bagian_belum_lunas', 'bagian_lunas'] as $bagianField) {
                $bagian = $request->input($bagianField);
                if (!is_array($bagian)) {
                    continue; // Skip validation if not provided
                }

                $givenKeys = array_keys($bagian);

                // Allow partial keys for preview
                foreach ($givenKeys as $key) {
                    if (!in_array($key, $requiredKeys)) {
                        $v->errors()->add($bagianField, "Kunci {$key} tidak valid.");
                        continue;
                    }

                    $item = $bagian[$key] ?? null;
                    if (!is_array($item)) {
                        $v->errors()->add("{$bagianField}.{$key}", "Bagian {$key} harus berupa objek.");
                        continue;
                    }

                    if (array_key_exists('aktif', $item) && !is_bool($item['aktif'])) {
                        $v->errors()->add("{$bagianField}.{$key}.aktif", "Field 'aktif' pada {$key} harus boolean.");
                    }

                    if (array_key_exists('teks', $item) && $item['teks'] !== null) {
                        if (!is_string($item['teks'])) {
                            $v->errors()->add("{$bagianField}.{$key}.teks", "Field 'teks' pada {$key} harus string.");
                        } elseif (mb_strlen($item['teks'], 'UTF-8') > 2000) {
                            $v->errors()->add("{$bagianField}.{$key}.teks", "Field 'teks' pada {$key} maksimal 2000 karakter.");
                        }
                    }

                    if ($key === 'kelas_pesanan') {
                        if (array_key_exists('format_baris', $item)) {
                            if (!is_string($item['format_baris']) || trim($item['format_baris']) === '') {
                                $v->errors()->add("{$bagianField}.kelas_pesanan.format_baris", "Format baris pada kelas_pesanan wajib diisi.");
                            } elseif (mb_strlen($item['format_baris'], 'UTF-8') > 255) {
                                $v->errors()->add("{$bagianField}.kelas_pesanan.format_baris", "Format baris maksimal 255 karakter.");
                            }
                        }
                    }
                }
            }
        });

        $validator->validate();
    }
}
