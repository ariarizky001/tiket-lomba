# PRD Revisi Tahap 8 — Status LUNAS / BELUM LUNAS di Template Invoice

> Instruksi kerja untuk agent. Ini revisi kecil pada fitur Template Invoice (Tahap 8) yang sudah terimplementasi. Kerjakan HANYA yang tertulis di sini. Tech stack dan larangan library sama dengan PRD Tahap 1. **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 1. Masalah
Template invoice belum memuat **status pembayaran**. Alur yang diinginkan:
1. Admin membuat pesanan. Invoice pertama adalah **tagihan**, statusnya **BELUM LUNAS**.
2. Setelah peserta membayar, admin menandai **LUNAS**, lalu membuka invoice lagi. **Template, nomor, kode, dan seluruh isi tetap sama**, hanya **status berubah menjadi LUNAS**.

Tidak ada invoice baru di database. Satu pesanan = satu record invoice; yang "dibuat lagi" adalah teksnya, dirender ulang dari data terbaru.

## 2. Scope Revisi
1. Placeholder status yang bisa dipakai di semua bagian template
2. Peringatan di editor bila template belum memuat status
3. Teks invoice selalu mengikuti status terbaru
4. Alur setelah **Tandai Lunas**: invoice langsung terbuka

## 3. Di Luar Scope
Mengubah isi bagian lain berdasarkan status (mis. menyembunyikan Ketentuan Pembayaran saat lunas), tanggal lunas, bukti bayar, invoice duplikat di database, perubahan skema database.

## 4. Kebutuhan Backend
1. **Placeholder status** pada `InvoiceRenderer`, berlaku di semua bagian:
   | placeholder | hasil |
   |---|---|
   | `{status}` | `LUNAS` atau `BELUM LUNAS` (dari `invoices.status` saat render) |
   | `{status_ikon}` | `✅` bila lunas, `⏳` bila belum lunas |
   Pastikan `{status}` benar-benar terisi (jangan tampil mentah sebagai `{status}`), dan keduanya tampil pada daftar placeholder yang dikirim endpoint `GET /api/events/{event}/invoice-template` serta pratinjau (data contoh: `BELUM LUNAS`).
2. `GET /api/invoices/{invoice}/teks` selalu membaca status **terbaru dari database** saat dipanggil. Tidak boleh ada cache atau teks tersimpan. Tambahkan header `Cache-Control: no-store` pada respons ini.
3. **Template bawaan** (`config/invoice_template.php`) bagian *Total Pembayaran* ditambah satu baris:
   ```
   📌 STATUS : {status_ikon} {status}
   ```
   Template yang sudah tersimpan di database **tidak diubah otomatis** (isinya milik admin).

## 5. Kebutuhan Frontend
1. **Editor template** (`InvoiceTemplateModal`): tambahkan `Tag` `{status}` dan `{status_ikon}` pada `PlaceholderHelp` (bisa diklik untuk menyisipkan seperti placeholder lain).
2. **Peringatan di editor**: bila **tidak ada** bagian aktif yang mengandung `{status}` atau `{status_ikon}`, tampilkan `Alert` kuning di atas editor: "Template belum menampilkan status LUNAS/BELUM LUNAS. Sisipkan placeholder {status}." Peringatan hanya informasi (tidak memblokir Simpan) dan hilang begitu placeholder ada.
3. **InvoiceView**: setiap dibuka selalu memanggil ulang `GET /api/invoices/{id}/teks`; tidak boleh memakai teks atau data invoice dari state lama. `Tag` status pada judul modal (hijau Lunas, merah Belum Lunas) mengikuti status terbaru.
4. **Tandai Lunas**: setelah berhasil mengubah status menjadi **lunas** (dari tabel invoice), tampilkan `message.success` lalu **otomatis membuka InvoiceView** invoice itu agar teks berstatus LUNAS langsung bisa disalin. Perilaku ini **tidak** berlaku saat dikembalikan ke belum lunas (cukup `message.success`).
5. Aksi **Lihat Invoice** di tabel tetap bisa dipakai kapan saja dan selalu menampilkan teks dengan status saat itu.

## 6. Tes Otomatis (pola Tahap 5)
Tambahkan pada `tests/Unit/InvoiceRendererTest.php` dan `tests/Feature/InvoiceTemplateTest.php`; `php artisan test` tetap hijau:
- `{status}` → `BELUM LUNAS` untuk invoice belum lunas, `LUNAS` untuk invoice lunas; `{status_ikon}` → `⏳` dan `✅`
- Render invoice yang sama sebelum dan sesudah ditandai lunas: **seluruh baris sama, kecuali baris yang memuat status**
- `PATCH /api/invoices/{id}/status` ke lunas, lalu `GET /api/invoices/{id}/teks` → memuat `LUNAS`; kembali ke belum lunas → memuat `BELUM LUNAS`
- Respons `GET .../teks` memiliki header `Cache-Control` berisi `no-store`
- Template bawaan memuat `{status}`

## 7. Acceptance Criteria (semua harus lolos)
- [ ] Menyisipkan `📌 STATUS : {status_ikon} {status}` pada bagian Total Pembayaran lalu Simpan: pratinjau dan invoice menampilkan baris status
- [ ] **Alur lengkap:** buat pesanan → InvoiceView menampilkan `📌 STATUS : ⏳ BELUM LUNAS` → **Tandai Lunas** → InvoiceView terbuka otomatis dan menampilkan `📌 STATUS : ✅ LUNAS`
- [ ] Selain baris status, **seluruh isi teks sama persis** antara invoice belum lunas dan lunas (nomor urut, kode, kelas pesanan, total)
- [ ] Tidak ada record invoice baru di database setelah tandai lunas (jumlah baris `invoices` tetap)
- [ ] Mengembalikan ke belum lunas lalu **Lihat Invoice** menampilkan `BELUM LUNAS` lagi (tidak memakai teks lama)
- [ ] Template tanpa `{status}`: muncul Alert peringatan di editor; setelah placeholder disisipkan Alert hilang
- [ ] `{status}` tidak pernah tampil mentah di invoice atau pratinjau
- [ ] Tag status pada judul InvoiceView mengikuti status terbaru
- [ ] Endpoint tanpa login → 401; tidak ada error di Console; nyaman di lebar 375px
- [ ] `php artisan test` hijau; tidak ada library baru; fitur Tahap 1 sampai 8 tetap berjalan

## 8. Output yang Diharapkan dari Agent
1. Kode perubahan (renderer, config, controller, `PlaceholderHelp`, `InvoiceTemplateModal`, `InvoiceView`, tombol Tandai Lunas, test)
2. Perintah yang perlu dijalankan (`php artisan config:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

---

# Lampiran A — Contoh Hasil yang Diharapkan
Bagian *Total Pembayaran* pada template uji: `💰 TOTAL PEMBAYARAN`⏎`👉 Rp {total_angka}`⏎`📌 STATUS : {status_ikon} {status}`

**Invoice pertama (tagihan):**
```
💰 TOTAL PEMBAYARAN
👉 Rp 4.500.000
📌 STATUS : ⏳ BELUM LUNAS
```
**Setelah ditandai lunas (invoice yang sama, dibuka lagi):**
```
💰 TOTAL PEMBAYARAN
👉 Rp 4.500.000
📌 STATUS : ✅ LUNAS
```
