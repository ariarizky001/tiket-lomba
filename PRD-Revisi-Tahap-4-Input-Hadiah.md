# PRD Revisi Tahap 4 — Input Hadiah Berbentuk Tabel

> Instruksi kerja untuk agent. Ini revisi bagian input hadiah pada tab Laporan (Tahap 4). Kerjakan HANYA yang tertulis di sini. Bagian 1 (tabel penjualan per sesi) dan bagian 3 (ringkasan) pada tab Laporan tidak berubah. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 1. Masalah
Form hadiah saat ini berupa satu card per sesi dengan daftar baris dinamis (`Form.List`), terlalu rumit untuk sekadar mengisi nominal. Admin ingin tampilan yang sederhana: **satu tabel, satu baris per sesi, lima kolom juara, tiap sel langsung bisa diisi.**

## 2. Tampilan yang Diinginkan
```
Nama Sesi     | Juara 1 | Juara 2 | Juara 3 | Juara 4 | Juara 5 | Total
Murai Batu A  | (isian) | (isian) | (isian) | (isian) | (isian) | Rp ...
Murai Batu B  | (isian) | (isian) | (isian) | (isian) | (isian) | Rp ...
Cucak Hijau   | (isian) | (isian) | (isian) | (isian) | (isian) | Rp ...
```
Sel dikosongkan = sesi itu tidak ada hadiah untuk juara tersebut. Sesi boleh tanpa hadiah sama sekali.

## 3. Scope Revisi
1. Ganti bagian 2 tab Laporan ("Hadiah per Sesi") dari card-per-sesi menjadi **tabel grid** di atas
2. Ganti endpoint simpan hadiah menjadi **satu endpoint simpan massal**
3. Skema database `prizes` **tidak berubah**

## 4. Di Luar Scope
Perubahan tabel `prizes`, endpoint `GET /api/events/{event}/report` (tetap, sudah memuat `prizes` per sesi), tabel penjualan, ringkasan, grafik, ekspor, library baru.

## 5. Backend
**Hapus** `PUT /api/sessions/{eventSession}/prizes`. **Ganti** dengan:

`PUT /api/events/{event}/prizes` (di grup `auth:sanctum`), menyimpan hadiah semua sesi event dalam **satu DB transaction**. Body:
```json
{
  "sesi": [
    { "event_session_id": 1, "juara": { "1": 500000, "2": 300000, "3": null, "4": null, "5": null } },
    { "event_session_id": 2, "juara": { "1": 400000, "2": null, "3": null, "4": null, "5": null } }
  ]
}
```
Aturan:
- Untuk tiap sesi yang dikirim: **ganti seluruh hadiahnya** (hapus semua baris `prizes` sesi itu, lalu buat baris baru hanya untuk juara yang bernilai > 0). `null`, kosong, atau `0` berarti **tidak ada hadiah** untuk juara itu.
- Sesi yang tidak ada di body tidak diubah.
- Setiap `event_session_id` harus **milik event di URL** (kalau tidak → 422).
- Validasi: `sesi` required|array; `sesi.*.event_session_id` required|integer|exists, distinct; `sesi.*.juara` required|array; kunci `juara` hanya `1` sampai `5`; nilai nominal nullable|integer|min:0|max:999999999. Error format standar Laravel (422).
- Respons sukses: sama dengan `GET /api/events/{event}/report` (agar frontend langsung memakai data terbaru).
- Pastikan kombinasi `(event_session_id, juara)` tetap unik dan tidak ada query N+1.

## 6. Frontend
**Struktur:** hapus `PrizeForm.js` (card per sesi). Tambah `resources/js/client/admin/components/report/PrizeGrid.js`, dan sesuaikan `ReportTab.js` agar bagian 2 memakai `PrizeGrid`.

**PrizeGrid**
- `Table` antd, `pagination={false}`, `scroll={{ x: true }}`, `size="small"`. Kolom **Nama Sesi** dibuat `fixed: 'left'` agar tetap terlihat saat digeser di mobile
- Kolom: Nama Sesi, **Juara 1, Juara 2, Juara 3, Juara 4, Juara 5**, **Total** (jumlah nominal satu baris, Rupiah, terhitung langsung saat mengetik)
- Tiap sel juara = `InputNumber` (min 0, tanpa tombol panah `controls={false}`, format ribuan dengan awalan `Rp`, `placeholder="-"`, lebar penuh sel). Kosong = tidak ada hadiah
- Baris footer (`Table.Summary`): total per kolom juara dan total keseluruhan
- Nilai awal diambil dari `sesi[].prizes` pada data laporan (`juara` → kolom yang sesuai). Sesi tanpa hadiah: semua sel kosong
- State grid disimpan lokal (objek `{ [sessionId]: { 1: n|null, ..., 5: n|null } }`); tidak memakai `Form.List`
- Tombol **Simpan Hadiah** (satu tombol, `type="primary"`, loading saat menyimpan) di atas atau bawah tabel, dan tombol **Batal Perubahan** (mengembalikan ke data terakhir dari server)
- Tampilkan `Tag` kuning "Ada perubahan belum disimpan" selama isian berbeda dari data server; tombol Simpan nonaktif bila tidak ada perubahan
- Simpan memanggil `PUT /events/{id}/prizes` dengan **semua sesi** pada tabel. Sukses → `message.success("Hadiah tersimpan")`, perbarui laporan (tabel penjualan dan ringkasan) dari respons, dan reset penanda perubahan. Gagal 422 → `message.error` berisi pesan API, isian **tidak** hilang
- Event tanpa sesi: bagian ini tidak ditampilkan (sudah ada aturan `Empty` pada bagian 1)
- Keyboard: `Tab` berpindah antar sel, tidak ada submit otomatis dengan `Enter`

**UX:** mobile-first (375px), teks UI berbahasa Indonesia, uang format Rupiah (`formatRupiah`).

## 7. Acceptance Criteria (semua harus lolos)
- [ ] Tab Laporan bagian 2 berupa tabel: baris = sesi, kolom = Juara 1 sampai 5 dengan isian langsung, plus kolom Total
- [ ] Tidak ada lagi form berbentuk card per sesi atau tombol "Tambah Juara"
- [ ] Mengisi beberapa sel pada beberapa sesi lalu **satu kali** klik Simpan Hadiah menyimpan semuanya
- [ ] Setelah refresh halaman, nominal yang disimpan tampil kembali pada sel yang benar
- [ ] Mengosongkan sel (atau mengisi 0) lalu simpan menghapus hadiah juara itu; mengosongkan satu baris penuh menghapus semua hadiah sesi tersebut
- [ ] Hadiah sebagian juara (mis. hanya Juara 1 dan 3) tersimpan dengan benar
- [ ] Sesi tanpa hadiah tampil dengan sel kosong tanpa error
- [ ] Total per baris, total per kolom, dan total keseluruhan terhitung langsung saat mengetik
- [ ] Tag "Ada perubahan belum disimpan" muncul saat ada perubahan dan hilang setelah simpan atau batal; Simpan nonaktif bila tidak ada perubahan
- [ ] **Batal Perubahan** mengembalikan isian ke data terakhir dari server
- [ ] Setelah simpan, ringkasan (Total Hadiah dan Laba Bersih) ikut diperbarui
- [ ] Mengirim `event_session_id` milik event lain, nominal negatif, atau juara di luar 1 sampai 5 → 422 dan isian di layar tidak hilang
- [ ] Gagal di tengah (error validasi) tidak menyimpan sebagian data (transaction)
- [ ] Kolom Nama Sesi tetap terlihat saat tabel digeser horizontal di lebar 375px
- [ ] Endpoint lama `PUT /api/sessions/{id}/prizes` sudah dihapus dan tidak ada kode frontend yang masih memakainya
- [ ] Endpoint tanpa login → 401; tidak ada error di Console; tidak ada library baru; Tahap 1 sampai 3 dan bagian lain Laporan tetap berjalan

## 8. Output yang Diharapkan dari Agent
1. Kode perubahan (controller, route, `PrizeGrid.js`, `ReportTab.js`, penghapusan `PrizeForm.js`)
2. Perintah yang perlu dijalankan (`php artisan route:clear`, `npm run dev`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria
