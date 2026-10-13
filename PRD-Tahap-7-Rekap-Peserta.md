# PRD Tahap 7 — Rekap Peserta (Per Sesi dengan Expand Qty)

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 7 di atas hasil Tahap 1 sampai 6. Jangan ubah yang sudah berjalan. Lingkungan Windows PowerShell.

## 1. Prasyarat
Tahap 1 sampai 6 selesai. Tech stack sama (React 17, antd 4.24, Laravel 10, Sanctum, Mix 6). Tidak boleh library baru.

## 2. Tujuan
Admin melihat daftar peserta per sesi dengan detail: siapa aja yang terdaftar, status pembayaran, kode invoice. Setiap tiket ditampilkan sebagai baris terpisah dengan prefix nomor urut (Budi 1, Budi 2, dst).

## 3. Scope Tahap 7
1. Tab baru **Rekap Peserta** di Ruang Event
2. Accordion/Card per sesi
3. Tabel peserta dengan expand qty → baris terpisah + prefix nomor
4. Filter: status (Semua/Lunas/Belum Lunas)
5. Search: nama peserta
6. Ringkasan per sesi: total peserta, total qty, total revenue

## 4. Di Luar Scope
Export Excel/CSV, print, perubahan pada tab lain, tabel baru di database.

## 5. Database
**Tidak ada perubahan.** Gunakan existing:
- `invoices`: nama_peserta, kode, status, paid_at, total, event_id
- `invoice_items`: event_session_id, qty, invoice_id
- `event_sessions`: id, nama, event_id

**Logic:**
Expand `invoice_items.qty` menjadi multiple rows saat render di frontend.

## 6. API Baru

**GET /api/events/{event}/participant-recap**

Response:
```json
{
  "sesi": [
    {
      "id": 1,
      "nama": "Sesi A",
      "peserta": [
        {
          "nama_peserta": "Budi",
          "urutan": 1,
          "kode": "EVT-0001",
          "status": "lunas",
          "total": 250000,
          "paid_at": "2026-10-13"
        },
        {
          "nama_peserta": "Budi",
          "urutan": 2,
          "kode": "EVT-0001",
          "status": "lunas",
          "total": 250000,
          "paid_at": "2026-10-13"
        },
        {
          "nama_peserta": "Sari",
          "urutan": 1,
          "kode": "EVT-0002",
          "status": "belum_lunas",
          "total": 100000,
          "paid_at": null
        }
      ],
      "summary": {
        "total_peserta": 3,
        "total_qty": 3,
        "total_revenue_lunas": 500000,
        "total_revenue_belum_lunas": 100000
      }
    }
  ]
}
```

**Penjelasan:**
- Setiap peserta yang beli qty > 1 ditampilkan sebagai multiple entries dengan `urutan` 1, 2, 3, ...
- `total` adalah total per invoice (sama untuk semua qty dari peserta yg sama)
- Anti N+1: gunakan eager load, bukan loop query

## 7. Frontend

**Struktur komponen:**
```
resources/js/client/admin/components/participant-recap/
├── ParticipantRecapTab.js        # main, handle data & filter
├── SessionAccordion.js           # accordion per sesi
└── ParticipantTable.js           # tabel peserta
```

**ParticipantRecapTab.js:**
- Load data dari API
- State: selectedStatus (Semua/Lunas/Belum Lunas), searchText
- Kirim filtered data ke SessionAccordion

**SessionAccordion.js:**
- Map setiap sesi jadi Collapse item
- Title: "Sesi A (3 peserta, 3 tiket, Rp 600.000)"
- Render ParticipantTable

**ParticipantTable.js:**
- Kolom: Nama (dengan prefix urutan), Kode, Status (Tag), Total, Paid_at
- Footer ringkasan per sesi
- Tampil nama dengan prefix: `{nama_peserta} {urutan}` (Budi 1, Budi 2)

**UX:**
- Mobile-first (xs=24, sm=24)
- Filter & search di toolbar atas
- Accordion default expand sesi pertama
- Empty state: "Belum ada peserta"

## 8. Tes Otomatis (ParticipantRecapTest.php)

Minimal test:
- API return data benar per sesi
- Expand qty: qty 2 jadi 2 entries dengan urutan 1, 2
- Filter status: hanya lunas/belum lunas
- Search nama: case-insensitive
- Anti N+1: query konstan
- Protected: tanpa login → 401

## 9. Acceptance Criteria

- [ ] Tab Rekap Peserta ada di EventRoom (urutan: Sesi, Peserta & Invoice, Laporan, Pengeluaran, **Rekap Peserta**)
- [ ] API return peserta per sesi dengan expand qty & prefix urutan
- [ ] Tabel tampil Nama (dengan prefix), Kode, Status, Total, Paid_at
- [ ] Filter status bekerja
- [ ] Search nama case-insensitive
- [ ] Ringkasan per sesi: Total Peserta, Total Qty, Total Revenue (Lunas & Belum Lunas)
- [ ] Contoh: Budi beli 2 tiket Sesi A → tampil "Budi 1" & "Budi 2"
- [ ] Anti N+1 verified di test
- [ ] Mobile friendly
- [ ] `php artisan test` hijau; no library baru; fitur Tahap 1-6 tetap jalan

## 10. Output yang Diharapkan

1. Controller + Route + API
2. Test file (ParticipantRecapTest.php)
3. Frontend components (3 file)
4. Integrasi ke EventRoom
5. Build & test hasil
6. Pernyataan Acceptance Criteria selesai

---

**Dimulai dari:** Backend controller & API endpoint
