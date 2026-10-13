# PRD — Tabel Peserta per Sesi (Rekap Peserta)

> Dokumentasi kebutuhan untuk fitur rekap peserta per sesi dengan tampilan tabel bergaris.

---

## 1. Ringkasan
Fitur **Rekap Peserta** menampilkan daftar peserta yang terdaftar di setiap sesi event. Setiap sesi ditampilkan sebagai tabel terpisah dengan garis border lengkap.

---

## 2. Lokasi di Aplikasi
- **Menu:** Ruang Event → Tab **Rekap Peserta** (tab paling kanan)
- **Route:** `/admin/events/:id` → Tab `Rekap Peserta`
- **API:** `GET /api/events/{event}/participant-recap`

---

## 3. Tampilan (UI/UX)

### 3.1 Struktur Halaman
```
┌─────────────────────────────────────────────────────────┐
│ Rekap Peserta per                    [Print/Simpan PDF] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ┌─ SESI 1 (3 peserta, 3 tiket) ──────────────────────┐ │
│ │ [Filter Status] [Search nama...]                    │ │
│ ├────┬────────────────────┬──────────────────────────┤ │
│ │ No │ Nama Peserta       │ Status                   │ │
│ ├────┼────────────────────┼──────────────────────────┤ │
│ │ 1  │ Aria               │ 🟢 Lunas                 │ │
│ ├────┼────────────────────┼──────────────────────────┤ │
│ │ 2  │ Budi #1            │ 🟡 Belum Lunas           │ │
│ ├────┼────────────────────┼──────────────────────────┤ │
│ │ 3  │ Budi #2            │ 🟡 Belum Lunas           │ │
│ └────┴────────────────────┴──────────────────────────┘ │
│                                                         │
│ ┌─ SESI 2 (2 peserta, 2 tiket) ──────────────────────┐ │
│ │ [Filter Status] [Search nama...]                    │ │
│ ├────┬────────────────────┬──────────────────────────┤ │
│ │ 1  │ Sari               │ 🟢 Lunas                 │ │
│ └────┴────────────────────┴──────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### 3.2 Komponen per Sesi
- **Header Card:** Nama sesi + summary (Total peserta, tiket, revenue)
- **Toolbar:** Filter status (Semua/Lunas/Belum Lunas) + Search nama
- **Tabel:** Bordered, 3 kolom

---

## 4. Kolom Tabel

| Kolom | Lebar | Align | Isi |
|-------|-------|-------|-----|
| No | 60px | Center | Nomor urut (1, 2, 3...) |
| Nama Peserta | 150px | Left | Nama + prefix `#` jika qty > 1 |
| Status | 120px | Center | Tag: 🟢 Lunas / 🟡 Belum Lunas |

### Logika Prefix Nama
- **qty = 1** → `Aria`
- **qty > 1** → `Budi #1`, `Budi #2`, `Budi #3`

---

## 5. Data Source (API)

### Endpoint
```
GET /api/events/{event}/participant-recap
```

### Response
```json
{
  "sesi": [
    {
      "id": 1,
      "nama": "Sesi A",
      "peserta": [
        {
          "nama_peserta": "Aria",
          "urutan": 1,
          "kode": "EVT-0001",
          "status": "lunas"
        },
        {
          "nama_peserta": "Budi",
          "urutan": 1,
          "kode": "EVT-0002",
          "status": "belum_lunas"
        },
        {
          "nama_peserta": "Budi",
          "urutan": 2,
          "kode": "EVT-0002",
          "status": "belum_lunas"
        }
      ],
      "summary": {
        "total_peserta": 3,
        "total_qty": 3,
        "total_revenue_lunas": 100000,
        "total_revenue_belum_lunas": 200000
      }
    }
  ]
}
```

---

## 6. Fitur Interaktif

| Fitur | Deskripsi |
|-------|-----------|
| **Filter Status** | Dropdown: Semua / Lunas / Belum Lunas (per sesi) |
| **Search** | Cari nama peserta case-insensitive (per sesi) |
| **Print/PDF** | Tombol "Print / Simpan PDF" → `window.print()` |
| **Loading** | Skeleton table saat fetch data |
| **Empty State** | "Tidak ada data yang sesuai" |

---

## 7. Print / PDF

### Print Layout
- **Header:** "REKAP PESERTA PER SESI" + timestamp
- **Semua sesi** tampil stacked vertikal
- **Table header** repeat di setiap halaman
- **Page break avoid** per sesi
- **Tag colors** print dengan benar
- **Hide:** Sidebar, header, toolbar, filter

### CSS Print (`resources/css/app.css`)
```css
@media print {
  .no-print { display: none !important; }
  .print-only { display: block !important; }
  .ant-table-thead { display: table-header-group !important; }
  .ant-tag { -webkit-print-color-adjust: exact; }
}
```

---

## 8. File Terkait

| File | Deskripsi |
|------|-----------|
| `resources/js/client/admin/components/participant-recap/ParticipantRecapTab.js` | Main component (load data, render sesi cards) |
| `resources/js/client/admin/components/participant-recap/ParticipantTable.js` | Table component per sesi |
| `app/Http/Controllers/ParticipantRecapController.php` | Backend API |
| `routes/api.php` | Route definition |
| `tests/Feature/ParticipantRecapTest.php` | 7 test cases |
| `resources/css/app.css` | Print styles |

---

## 9. Test Coverage

```bash
php artisan test --filter=ParticipantRecapTest
```

| Test Case | Deskripsi |
|-----------|-----------|
| `get_participant_recap_basic` | Basic response structure |
| `expand_qty_to_separate_rows` | Qty > 1 → multiple rows dengan urutan |
| `multiple_peserta_per_sesi` | Multiple participants per session |
| `multiple_sessions` | Multiple sessions in one event |
| `status_summary_calculation` | Revenue calculation benar |
| `protected_endpoint_requires_auth` | 401 tanpa login |
| `no_n1_queries` | Anti N+1 verified |

---

## 10. Acceptance Criteria

- [x] Tab "Rekap Peserta" ada di EventRoom
- [x] Tabel per sesi dengan border lengkap
- [x] Kolom: No, Nama Peserta (prefix #), Status
- [x] Prefix `#` hanya tampil jika qty > 1
- [x] Filter status & search per sesi berfungsi
- [x] Summary tags: Total, Lunas, Belum, Peserta/Tiket
- [x] Print/PDF layout benar
- [x] Anti N+1 verified
- [x] 76 test passed (termasuk 7 test ParticipantRecapTest)

---

## 11. Perintah Eksekusi

```powershell
# Build frontend
npm run dev

# Run test
php artisan test --filter=ParticipantRecapTest

# Full test suite
php artisan test
```

---

**Status: ✅ Production Ready**