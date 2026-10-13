# PRD Tahap 4 — Laporan Penjualan & Hadiah per Sesi

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 4 di atas hasil Tahap 1 sampai 3 (termasuk revisi dropdown sesi dan nama sesi invoice). Jangan ubah yang sudah berjalan kecuali disebut di sini. Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 sampai 3 lolos: login, dashboard, CRUD event, Ruang Event, CRUD sesi, peserta dan invoice (harga snapshot, status lunas/belum lunas). Tech stack dan larangan library sama persis dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru** (tanpa library chart). Lingkungan Windows PowerShell.

## 2. Tujuan
Satu halaman **Laporan** per event dengan urutan tampilan tetap:
1. **Data semua sesi**: jumlah terjual dan pemasukan per sesi (**hanya dari invoice lunas**)
2. **Form rincian hadiah per sesi** (juara 1 sampai 5, custom, tidak wajib diisi di semua sesi)
3. **Ringkasan**: total pemasukan, total hadiah, dan sisa/laba

## 3. Scope Tahap 4
1. Tabel dan API `prizes`
2. Endpoint laporan (agregasi di server)
3. Tab **Laporan** di Ruang Event berisi tiga bagian di atas
4. **Perubahan tab Ruang Event:** tab **Hadiah** (yang sebelumnya nonaktif) **dihapus** karena form hadiah kini berada di dalam tab Laporan. Tab akhir: Sesi, Peserta & Invoice, Laporan (semuanya aktif)

## 4. Di Luar Scope
Grafik/chart, ekspor Excel/PDF/cetak laporan, nama pemenang, hadiah berupa barang, hitung otomatis persentase hadiah, laporan lintas event, halaman public, realtime.

## 5. Database
**prizes** (model `Prize`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| event_session_id | FK → event_sessions.id | `cascadeOnDelete` |
| juara | unsignedTinyInteger | 1 sampai 5 |
| nominal | unsignedInteger | Rupiah, ≥ 1 |
| timestamps | | |

Index unik `(event_session_id, juara)`. Relasi: `EventSession hasMany Prize`, `Prize belongsTo EventSession`.

Aturan data:
- Sesi **boleh tanpa hadiah** (tidak ada baris di `prizes`).
- Satu sesi boleh punya hadiah untuk sebagian juara saja, misalnya hanya juara 1 dan 3 (tidak harus berurutan).
- Menghapus sesi ikut menghapus hadiahnya.

## 6. Definisi Perhitungan (WAJIB konsisten di server dan UI)
- **Terjual** = jumlah `qty` seluruh invoice sesi tersebut, **lunas dan belum lunas** (sama dengan perhitungan kuota Tahap 3).
- **Sisa** = `kuota − terjual`.
- **Pemasukan sesi** = jumlah `subtotal` item dari invoice berstatus **lunas saja**. Memakai harga snapshot saat transaksi, sehingga bila harga sesi diubah setelah ada transaksi, angka lama tidak berubah. Invoice belum lunas **tidak** dihitung sebagai pemasukan.
- **Belum lunas** = jumlah `subtotal` item dari invoice berstatus `belum_lunas` (uang yang belum diterima). Ditampilkan terpisah, tidak masuk pemasukan dan tidak dipakai pada selisih/laba. Pemasukan + Belum lunas = jumlah subtotal seluruh invoice (terjual × harga snapshot).
- **Total hadiah sesi** = jumlah `nominal` seluruh hadiah sesi.
- **Selisih sesi** = `pemasukan − total_hadiah`.
- **Total event**: jumlah dari semua sesi; **Laba bersih** = total pemasukan − total hadiah.

## 7. Routes & API
Di dalam grup `auth:sanctum`:

**`GET /api/events/{event}/report`**
```json
{
  "sesi": [
    {
      "id": 1, "nama": "MURAI BATU B NYAWIJI", "harga": 100000, "kuota": 24,
      "terjual": 10, "sisa": 14,
      "pemasukan": 800000, "belum_lunas": 200000,
      "prizes": [ { "juara": 1, "nominal": 500000 }, { "juara": 2, "nominal": 200000 } ],
      "total_hadiah": 700000, "selisih": 100000
    }
  ],
  "ringkasan": {
    "total_kuota": 24, "total_terjual": 10, "total_sisa": 14,
    "pemasukan": 800000, "belum_lunas": 200000,
    "total_hadiah": 700000, "laba": 100000,
    "jumlah_invoice": 6, "invoice_lunas": 5, "invoice_belum_lunas": 1
  }
}
```
Semua nilai uang/jumlah berupa **integer** (0 bila kosong, bukan `null`). Sesi tanpa penjualan atau tanpa hadiah tetap muncul (nilai 0, `prizes: []`). `prizes` diurutkan `juara` asc. Urutan sesi `id` asc. Gunakan query agregat (`SUM`/`withSum` dengan kondisi) dan eager load, **tanpa N+1**.

**`PUT /api/sessions/{eventSession}/prizes`** → mengganti **seluruh** hadiah sesi (replace-all dalam DB transaction). Body:
```json
{ "prizes": [ { "juara": 1, "nominal": 500000 }, { "juara": 2, "nominal": 300000 } ] }
```
`prizes: []` menghapus semua hadiah sesi itu. Respons: objek sesi dalam format elemen `sesi[]` di atas.

**Validasi PUT:** `prizes` required|array|max:5; `prizes.*.juara` required|integer|between:1,5|distinct; `prizes.*.nominal` required|integer|min:1|max:999999999. Error memakai format standar Laravel (422).

## 8. Kebutuhan Frontend
**Struktur tambahan**
```
resources/js/client/admin/components/report/
├── ReportTab.js        # memuat data, menyusun tiga bagian
├── SessionReportTable.js
├── PrizeForm.js        # form hadiah satu sesi (inline)
└── ReportSummary.js
```
Redux tetap hanya untuk auth (data cukup di state lokal).

**ReportTab**: muat `GET /events/{id}/report` saat tab dibuka, tombol **Muat Ulang** di pojok atas. Tampilan berurutan dari atas ke bawah:

**Bagian 1: Data Penjualan per Sesi** (`SessionReportTable`)
- `Table` antd (`scroll={{ x: true }}`), kolom: Sesi, Harga, Kuota, **Terjual**, Sisa, **Terisi** (`Progress` persen `terjual/kuota`, bulat), **Pemasukan (Lunas)** (Rupiah), Belum Lunas (Rupiah, teks abu-abu kecil)
- Baris ringkasan total di bawah tabel (`Table.Summary`): total kuota, terjual, sisa, pemasukan, belum lunas
- Event tanpa sesi → `Empty` "Belum ada sesi" dan bagian 2 dan 3 tidak ditampilkan

**Bagian 2: Hadiah per Sesi** (`PrizeForm`)
- Judul bagian "Rincian Hadiah per Sesi" dengan keterangan kecil "Opsional, boleh dikosongkan untuk sesi yang tidak ada hadiahnya"
- Satu `Card` per sesi (grid `Row/Col` xs=24, md=12), berisi form inline:
  - `Form.List` maksimal 5 baris; tiap baris `Select` juara (Juara 1 sampai Juara 5, juara yang sudah dipakai di baris lain dinonaktifkan) + `InputNumber` nominal (min 1, format ribuan, step 50.000) + tombol hapus baris
  - Tombol **Tambah Juara** (nonaktif bila sudah 5 baris); juara baru otomatis terisi nomor terkecil yang belum dipakai
  - **Total hadiah sesi** terhitung langsung saat mengetik
  - Tombol **Simpan Hadiah** per sesi (loading saat menyimpan)
- Form terisi dari `prizes` sesi yang ada. Sesi tanpa hadiah tampil dengan form kosong (tanpa baris) tanpa peringatan. Mengosongkan semua baris lalu simpan menghapus seluruh hadiah sesi
- Simpan memanggil `PUT /sessions/{id}/prizes`; sukses → `message.success` dan **muat ulang laporan** (tanpa mereset isian card sesi lain yang belum disimpan). Error 422 tampil di `message.error`

**Bagian 3: Ringkasan** (`ReportSummary`)
- Baris `Statistic` antd (`Row/Col` xs=12, md=6): **Total Pemasukan (Lunas)**, **Total Hadiah**, **Laba Bersih** (hijau bila ≥ 0, merah bila negatif), **Belum Lunas**
- Teks kecil: jumlah invoice total, lunas, dan belum lunas
- Angka ringkasan selalu sama dengan jumlah baris sesi di atas

**UX:** mobile-first (375px), semua teks UI berbahasa Indonesia, semua uang format Rupiah (`formatRupiah`).

## 9. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `prizes` ada dengan FK cascade dan index unik
- [ ] Ruang Event hanya memiliki tab Sesi, Peserta & Invoice, dan Laporan (tab Hadiah terpisah tidak ada), semuanya berfungsi
- [ ] Tab Laporan menampilkan urutan: tabel sesi → form hadiah per sesi → ringkasan
- [ ] Tabel menampilkan semua sesi (termasuk yang belum terjual, bernilai 0), dengan Terjual dan Pemasukan benar
- [ ] Pemasukan hanya dari invoice lunas (uji: sesi harga 100.000 dengan 3 tiket, 2 lunas dan 1 belum lunas → Terjual 3, Pemasukan Rp 200.000, Belum Lunas Rp 100.000)
- [ ] Menandai invoice lunas lalu Muat Ulang Laporan memindahkan nominalnya dari Belum Lunas ke Pemasukan; menandai kembali belum lunas membalikkannya
- [ ] Mengubah harga sesi setelah transaksi tidak mengubah Pemasukan dan Belum Lunas yang lama
- [ ] Mengatur hadiah juara 1 sampai 3 pada satu sesi berhasil dan tetap tampil setelah Muat Ulang atau refresh halaman
- [ ] Sesi tanpa hadiah menampilkan form kosong tanpa error, dan laporan tetap berfungsi
- [ ] Hadiah sebagian juara (mis. juara 1 dan 3) diterima
- [ ] Juara kembar, juara di luar 1 sampai 5, nominal < 1, atau lebih dari 5 baris ditolak
- [ ] Mengosongkan semua baris lalu simpan menghapus seluruh hadiah sesi
- [ ] Total hadiah sesi di form terhitung langsung; Laba Bersih = total pemasukan (lunas) − total hadiah; negatif tampil merah
- [ ] Menyimpan hadiah satu sesi tidak menghapus isian yang belum disimpan di card sesi lain
- [ ] Menghapus sesi ikut menghapus hadiahnya
- [ ] Tidak ada N+1 pada endpoint report; semua endpoint tanpa login → 401
- [ ] Nyaman di lebar layar 375px; tidak ada error di Console
- [ ] Tidak ada library baru; fitur Tahap 1 sampai 3 tetap berjalan

## 10. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, model, controller, route, komponen React)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `npm run dev`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria
