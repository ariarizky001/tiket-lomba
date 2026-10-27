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
                'bagian' => $default['bagian'],
                'is_default' => true,
                'placeholder' => $this->getPlaceholders(),
            ]);
        }

        return response()->json([
            'pemisah' => $template->pemisah,
            'bagian' => $template->bagian,
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
                'bagian' => $request->bagian,
            ]
        );

        return response()->json([
            'pemisah' => $template->pemisah,
            'bagian' => $template->bagian,
            'is_default' => false,
            'placeholder' => $this->getPlaceholders(),
        ]);
    }

    public function preview(Request $request, Event $event)
    {
        $this->validateTemplateRequest($request);

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

        $mockInvoice = [
            'nama_peserta' => 'Rizal Medan',
            'nomor' => 277,
            'kode' => 'SHC-0277',
            'created_at' => now(),
            'total' => $totalMock,
            'status' => 'belum_lunas',
            'items' => $mockItems,
        ];

        $templateData = [
            'pemisah' => $request->pemisah,
            'bagian' => $request->bagian,
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
                'bagian' => $template->bagian,
            ];
        } else {
            $templateData = config('invoice_template');
        }

        $teks = $this->renderer->render($templateData, $event, $invoice);

        return response()->json(['teks' => $teks]);
    }

    private function validateTemplateRequest(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'pemisah' => 'required|string|max:50',
            'bagian' => 'required|array',
        ]);

        $validator->after(function ($v) use ($request) {
            $bagian = $request->input('bagian');
            if (!is_array($bagian)) {
                return;
            }

            $requiredKeys = InvoiceRenderer::SECTION_KEYS;
            $givenKeys = array_keys($bagian);

            if (count($givenKeys) !== 8 || array_diff($requiredKeys, $givenKeys) !== [] || array_diff($givenKeys, $requiredKeys) !== []) {
                $v->errors()->add('bagian', 'Bagian harus berisi tepat 8 kunci standar.');
                return;
            }

            foreach ($requiredKeys as $key) {
                $item = $bagian[$key] ?? null;
                if (!is_array($item)) {
                    $v->errors()->add("bagian.{$key}", "Bagian {$key} harus berupa objek.");
                    continue;
                }

                if (!array_key_exists('aktif', $item) || !is_bool($item['aktif'])) {
                    $v->errors()->add("bagian.{$key}.aktif", "Field 'aktif' pada {$key} harus boolean.");
                }

                if (array_key_exists('teks', $item) && $item['teks'] !== null) {
                    if (!is_string($item['teks'])) {
                        $v->errors()->add("bagian.{$key}.teks", "Field 'teks' pada {$key} harus string.");
                    } elseif (mb_strlen($item['teks'], 'UTF-8') > 2000) {
                        $v->errors()->add("bagian.{$key}.teks", "Field 'teks' pada {$key} maksimal 2000 karakter.");
                    }
                }

                if ($key === 'kelas_pesanan') {
                    if (!array_key_exists('format_baris', $item) || !is_string($item['format_baris']) || trim($item['format_baris']) === '') {
                        $v->errors()->add('bagian.kelas_pesanan.format_baris', "Format baris pada kelas_pesanan wajib diisi.");
                    } elseif (mb_strlen($item['format_baris'], 'UTF-8') > 255) {
                        $v->errors()->add('bagian.kelas_pesanan.format_baris', "Format baris maksimal 255 karakter.");
                    }
                }
            }
        });

        $validator->validate();
    }
}
