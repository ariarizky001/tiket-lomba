# PRD Rekap Peserta — Tabel per Sesi & Mode Layar Penuh

> Instruksi kerja untuk agent. Ini perbaikan dan penyempurnaan halaman **Rekap Peserta** yang sudah dibuat, bukan fitur dari nol. Kerjakan HANYA yang tertulis di sini. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru** (tanpa styled-components; CSS lewat `<style>`/style inline pada komponen, atau satu file CSS lewat Mix). Lingkungan Windows PowerShell.

## 1. Masalah
Halaman Rekap Peserta saat ini hanya menampilkan **teks polos vertikal** yang menyebut nama sesi, tanpa tabel. Yang dibutuhkan: **satu tabel per sesi**, tersusun menyamping seperti papan rekap, berurutan menurut tanggal lunas, dan bisa dibuka dalam mode layar penuh di tab baru.

Dugaan penyebab (periksa lalu ganti implementasinya): komponen hanya me-render `sessions.map(s => <div>{s.nama}</div>)` tanpa elemen tabel; data peserta tidak dipetakan ke baris; atau bentuk data API tidak cocok dengan yang dibaca komponen. Cari halaman yang ada (cari kata `rekap` di `resources/js/client/admin/`), **pakai ulang route dan tab-nya**, dan ganti isi tampilannya sesuai PRD ini.

## 2. Prasyarat
Tahap 1 sampai 4 berfungsi (sesi dengan `kuota`, invoice dengan `status` dan `paid_at`, item invoice dengan `qty`).

## 3. Scope
1. Endpoint data rekap (backend)
2. Halaman **Rekap Peserta** di Ruang Event: grid tabel per sesi
3. **Mode layar penuh** di tab baru: halaman tersendiri yang hanya menampilkan tabel-tabel itu
4. Tes otomatis endpoint

## 4. Di Luar Scope
Grid slot/gantangan yang bisa diklik, input atau edit data dari halaman ini (hanya-baca), cetak/ekspor PDF, hadiah, laporan keuangan, perubahan skema database.

## 5. Aturan Tampilan Data (WAJIB)
**Satu tabel per sesi**, kolom hanya dua: **No** dan **Nama**.

**Header tabel** = nama sesi (dari database, apa adanya) dan harganya (format Rupiah), contoh:
```
MURAI BATU B NYAWIJI
Rp 100.000
```

**Jumlah baris = `kuota` sesi** (misalnya 24). Baris yang belum terisi tetap tampil: kolom No berisi nomor, kolom Nama kosong.

**Satu baris = satu tiket.** Peserta yang membeli `qty` tiket pada sesi itu muncul **sebanyak `qty` baris berturut-turut** dengan nama yang sama.

**Urutan baris** (nomor mengikuti urutan ini, mulai 1):
1. Invoice **lunas** lebih dulu, diurutkan menurut **`paid_at` paling awal** (first-to-pay); jika sama, `invoice.id` terkecil
2. Setelah semua yang lunas, invoice **belum lunas**, diurutkan menurut waktu pesanan masuk (`created_at` paling awal; jika sama, `id` terkecil)
3. Baris kosong di paling bawah

Menandai invoice lunas memindahkan barisnya ke kelompok lunas sesuai `paid_at`; mengembalikan ke belum lunas memindahkannya ke kelompok belum lunas.

**Warna nama:** **lunas = hitam**, **belum lunas = merah** (`#cf1322`). Tidak ada keterangan teks tambahan, ikon, atau kolom lain.

## 6. Backend
`GET /api/events/{event}/rekap-peserta` (grup `auth:sanctum`). Server yang menyusun urutan dan memecah `qty` menjadi baris, agar logikanya satu sumber.
```json
{
  "sesi": [
    {
      "id": 1,
      "nama": "MURAI BATU B NYAWIJI",
      "harga": 100000,
      "kuota": 24,
      "peserta": [
        { "no": 1, "invoice_id": 12, "nama": "Sari", "status": "lunas" },
        { "no": 2, "invoice_id": 11, "nama": "Budi", "status": "lunas" },
        { "no": 3, "invoice_id": 11, "nama": "Budi", "status": "lunas" },
        { "no": 4, "invoice_id": 13, "nama": "Andi", "status": "belum_lunas" }
      ]
    }
  ]
}
```
- `peserta` hanya memuat baris yang terisi (panjangnya = jumlah tiket terjual); baris kosong dilengkapi oleh frontend sampai `kuota`
- Sesi tanpa peserta tetap muncul dengan `peserta: []`; urutan sesi `id` asc
- `status` hanya `lunas` atau `belum_lunas`; `harga` dan `kuota` integer
- Hanya data event pada URL; event tidak ada → 404
- Gunakan query yang di-*eager load* dan **tanpa N+1** (jumlah query konstan terhadap banyaknya sesi/invoice)
- Endpoint hanya-baca; tidak mengubah apa pun di database

## 7. Frontend

**Struktur tambahan/perubahan**
```
resources/js/client/admin/components/rekap/
├── RekapPeserta.js          # halaman di dalam Ruang Event (ganti implementasi lama)
├── RekapBoard.js            # grid tabel per sesi (dipakai kedua halaman)
└── RekapDisplay.js          # halaman layar penuh tab baru
```
Redux tetap hanya untuk auth.

**RekapBoard (komponen bersama)**
- Menerima `sesi[]` dari endpoint di atas, merender satu **tabel HTML sungguhan** (`<table>` dengan `<thead>` dan `<tbody>`, border tipis, nomor kolom rata tengah) di dalam kartu bergaris (di halaman Ruang Event dapat memakai `Card` antd; di layar penuh memakai kotak sederhana). **Dilarang** merender daftar sebagai teks/`<div>` vertikal biasa
- `thead`: satu baris berisi nama sesi (tebal) dan harga di bawahnya, mencakup dua kolom (`colSpan=2`)
- `tbody`: tepat `kuota` baris; baris terisi memakai data `peserta` menurut `no`; sisa baris diisi nomor berurutan dengan nama kosong
- **Tata letak:** CSS Grid, **5 tabel per baris layar** (`grid-template-columns: repeat(5, minmax(0, 1fr))`). Sesi ke-6 dan seterusnya otomatis turun ke baris di bawahnya (5 per baris, tabel dalam satu baris berukuran sama). Teks nama dipotong dengan elipsis bila kepanjangan (`white-space: nowrap; overflow: hidden; text-overflow: ellipsis`), `title` berisi nama lengkap
- **Responsif** (hanya untuk halaman di Ruang Event): ≥ 1200px → 5 kolom; 768 sampai 1199px → 3 kolom; di bawah 768px → 1 kolom (tabel tidak mengecil sampai tak terbaca)
- Font kecil dan baris rapat agar kuota sampai 24 baris muat; ukuran huruf nama sama untuk semua sesi

**RekapPeserta (halaman di Ruang Event)**
- Tab baru **Rekap Peserta** pada `EventRoom`, setelah tab Peserta & Invoice (tab lain tidak berubah)
- Memuat `GET /events/{id}/rekap-peserta` saat tab dibuka; tombol **Muat Ulang**; `Spin` saat memuat; sesi kosong → `Empty` "Belum ada sesi"
- Tombol **Layar Penuh** yang membuka **tab baru** ke `/admin/events/{id}/rekap-display` (`target="_blank"`, `rel="noopener"`)

**RekapDisplay (layar penuh, tab baru)**
- Route `/admin/events/:id/rekap-display` didaftarkan **di luar `AppLayout`** (tanpa navbar/header/sidebar/footer/menu). Tetap memerlukan login: belum login → arahkan ke `/login` (cookie sesi yang sama dengan tab utama membuatnya langsung bisa dibuka)
- Isi halaman **hanya `RekapBoard`**, memenuhi seluruh layar dengan margin kecil (± 8px), latar putih, tanpa judul, tanpa teks lain
- **Muat dalam satu layar:** tinggi baris tabel dihitung agar seluruh baris grid muat di tinggi layar (`100vh`). Hitung: `tinggi per baris ≈ (100vh − margin − tinggi header) / (kuota terbesar × jumlah baris grid)`, dengan batas minimum 18px per baris; bila batas minimum terlampaui (sesi terlalu banyak), halaman boleh di-scroll vertikal. Ukuran huruf mengikuti tinggi baris (minimum 10px)
- Di layar penuh selalu **5 kolom** pada lebar ≥ 1024px; di bawah itu 3 kolom
- **Kontrol melayang** kecil di pojok kanan atas (semi-transparan): **Layar Penuh** (toggle Fullscreen API browser, `Esc` untuk keluar) dan **Muat Ulang**. Saat browser dalam mode fullscreen, kontrol disembunyikan dan muncul kembali 3 detik setiap kali mouse digerakkan
- **Pembaruan otomatis** tiap 15 detik (konstanta yang mudah diubah), dijeda saat tab tidak terlihat (`document.hidden`), tanpa kedip layar (data lama tetap tampil sampai data baru tiba). Gagal memuat → data lama tetap tampil, tanpa error yang menutupi layar
- Hanya-baca; tidak ada tombol aksi lain

**UX:** semua teks UI berbahasa Indonesia; urutan dan warna sama persis di kedua halaman karena memakai `RekapBoard` yang sama.

## 8. Tes Otomatis (pola Tahap 5)
`tests/Feature/RekapPesertaTest.php` (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`), `php artisan test` tetap hijau:
- Urutan benar: lunas menurut `paid_at` terawal, lalu belum lunas menurut `created_at` terawal; nomor berurutan mulai 1
- `qty` dipecah menjadi baris sebanyak `qty` dengan nama yang sama berturut-turut
- Mengubah status invoice (lunas ↔ belum lunas) memindahkan barisnya sesuai aturan
- Sesi tanpa peserta mengembalikan `peserta: []`; hanya data event pada URL; event tidak ada → 404
- `status` hanya `lunas`/`belum_lunas`; `harga` dan `kuota` bertipe integer
- Tanpa login → 401 JSON
- Anti N+1: jumlah query sama pada data 3 sesi dan 15 sesi

## 9. Acceptance Criteria (semua harus lolos)
- [ ] Halaman Rekap Peserta menampilkan **tabel sungguhan** per sesi (bukan teks vertikal), dengan kolom No dan Nama saja
- [ ] Header tiap tabel = nama sesi dari database dan harganya (format Rupiah)
- [ ] Jumlah baris tiap tabel = `kuota` sesi; baris kosong tampil dengan nomor dan nama kosong
- [ ] Uji data (Sesi A, kuota 24): **Sari** (A×1, lunas, `paid_at` 09:00), **Budi** (A×2, lunas, `paid_at` 10:00), **Andi** (A×1, belum lunas) → baris: 1 Sari, 2 Budi, 3 Budi, 4 Andi, 5 sampai 24 kosong
- [ ] Nama peserta lunas berwarna **hitam**, belum lunas berwarna **merah**
- [ ] Menandai Andi lunas (setelah Budi) lalu Muat Ulang: Andi pindah ke posisi 5, berwarna hitam; mengembalikannya ke belum lunas memindahkannya ke posisi setelah semua yang lunas
- [ ] Di layar ≥ 1200px, 5 tabel tampil menyamping dalam satu baris; sesi ke-6 dan seterusnya di baris bawahnya; di 375px berupa 1 kolom yang terbaca
- [ ] Tombol **Layar Penuh** membuka **tab baru** `/admin/events/{id}/rekap-display`
- [ ] Tab layar penuh hanya berisi tabel-tabel (tanpa navbar, header, menu, judul, atau teks lain) selain kontrol melayang kecil
- [ ] Pada layar desktop, 5 sesi dengan kuota 24 muat dalam satu layar tanpa scroll; lebih dari 5 sesi tetap rapi di baris berikutnya
- [ ] Kontrol Layar Penuh mengaktifkan fullscreen browser, `Esc` keluar, dan kontrol tersembunyi lalu muncul kembali saat mouse digerakkan
- [ ] Data memperbarui sendiri tiap 15 detik tanpa kedip; berhenti saat tab tidak terlihat
- [ ] Membuka URL layar penuh tanpa login → diarahkan ke login; endpoint tanpa login → 401
- [ ] Nama sangat panjang dipotong elipsis tanpa merusak tata letak
- [ ] Tidak ada N+1; `php artisan test` hijau; tidak ada error di Console
- [ ] Tidak ada library baru; fitur Tahap 1 sampai 6 tetap berjalan

## 10. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (controller, route, komponen React, test), dan penghapusan implementasi lama yang menampilkan teks polos
2. **Penyebab tampilan teks vertikal yang ditemukan** (satu sampai dua kalimat)
3. Perintah yang perlu dijalankan (`php artisan route:clear`, `npm run dev`, `php artisan test`)
4. Daftar asumsi (jika ada)
5. Hasil centang Acceptance Criteria
