# PRD Tahap 5 — Pengujian Menyeluruh & Hardening Ringan

> Instruksi kerja untuk agent. Tahap ini **tidak menambah fitur**. Tugasnya: menulis tes otomatis backend, memeriksa keamanan dan performa, memperbaiki bug yang ditemukan, dan membuat laporan uji. Tech stack sama dengan PRD Tahap 1. **Tidak boleh menambah library** (PHPUnit sudah bawaan Laravel 10; `composer audit` dan `npm audit` adalah perintah bawaan). Lingkungan Windows PowerShell.

## 1. Prasyarat
Tahap 1 sampai 4 (beserta revisi dropdown sesi, nama sesi invoice, dan input hadiah berbentuk tabel) sudah terbuild dan berjalan.

## 2. Tujuan
Memastikan fitur utama benar secara perhitungan (kuota, total, pemasukan), aman dari kesalahan umum, dan stabil dipakai di acara nyata.

## 3. Scope
1. Tes otomatis backend (PHPUnit Feature Test) untuk seluruh aturan bisnis
2. Pemeriksaan keamanan dan hardening ringan (daftar tertutup di bagian 6)
3. Pemeriksaan performa dasar (N+1, index)
4. Perbaikan bug yang ditemukan (aturan di bagian 8)
5. Laporan uji `docs/TEST-REPORT.md`
6. Skenario uji manual (UAT) di Lampiran A, dijalankan oleh pemilik proyek

## 4. Di Luar Scope
Fitur baru, deploy ke server produksi, tes otomatis frontend (tanpa library baru, frontend diuji manual), manajemen role/user, pembayaran sebagian, ekspor PDF/Excel.

## 5. Tes Otomatis Backend
**Setup (WAJIB agar database dev tidak terhapus)**
- Buat database terpisah `tiketlomba_test` (MySQL, phpMyAdmin)
- Atur `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=tiketlomba_test`, `CACHE_DRIVER=array`, `SESSION_DRIVER=array`
- Setiap test class memakai `RefreshDatabase`
- Autentikasi di test dengan `Sanctum::actingAs($user)`
- Buat factory untuk `Event`, `EventSession`, `Invoice`, `InvoiceItem`, `Prize` bila belum ada
- Jalankan dengan `php artisan test`; semua harus hijau

**Cakupan minimal (satu test minimum per baris)**

*Auth & akses*
- Login benar → 200 dan data user; password salah → 422
- Logout menghapus sesi
- **Setiap** endpoint `/api/*` yang dilindungi tanpa login → 401 JSON (bukan redirect, bukan 500)

*Event*
- CRUD + validasi (`nama` dan `tanggal` wajib)
- Hapus event yang punya invoice → 422; event tanpa invoice terhapus beserta sesinya

*Sesi*
- CRUD + validasi; nama kembar di event sama ditolak, di event berbeda diperbolehkan; harga negatif dan kuota < 1 ditolak
- `terjual` dan `sisa` benar dan bertipe integer (0, bukan `null`)
- Kuota tidak boleh diturunkan di bawah terjual; hapus sesi yang punya invoice → 422

*Invoice*
- Buat dengan 2 sesi (qty berbeda): subtotal, total, dan harga snapshot benar
- Server **mengabaikan** harga, subtotal, dan total kiriman klien
- Kode format `INISIAL-0001`, berurutan per event, **tidak dipakai ulang** setelah hapus
- Kuota: beli tepat sebesar sisa → berhasil; satu tiket lebih → 422 dengan pesan per sesi
- `event_session_id` milik event lain → 422; sesi duplikat dalam satu invoice → 422; qty 0 → 422
- Edit invoice tidak menghitung ganda qty miliknya sendiri; edit mengubah total
- Toggle status: lunas mengisi `paid_at`, belum lunas mengosongkannya
- Hapus invoice mengembalikan kuota
- Ubah harga sesi setelah transaksi tidak mengubah invoice lama
- Filter `status` dan pencarian `q` bekerja; `q` berisi `' OR 1=1 --` tidak merusak query dan tidak membocorkan data
- Respons setiap item memuat `session_nama`

*Hadiah*
- Simpan massal `PUT /api/events/{event}/prizes`: nilai tersimpan pada juara yang benar
- `null`, kosong, atau `0` menghapus hadiah juara itu; baris kosong penuh menghapus semua hadiah sesi
- Hadiah sebagian juara (mis. juara 1 dan 3) diterima
- Sesi milik event lain → 422; nominal negatif atau kunci juara di luar 1 sampai 5 → 422 dan **tidak ada data berubah** (transaction)
- Hapus sesi menghapus hadiahnya (cascade)

*Laporan*
- Terjual menghitung invoice lunas **dan** belum lunas; Sisa = kuota − terjual
- Pemasukan hanya dari invoice **lunas**; Belum Lunas hanya dari invoice belum lunas
- Pemasukan memakai harga snapshot (ubah harga sesi, angka lama tetap)
- Total hadiah, selisih sesi, dan laba benar; `ringkasan` = jumlah baris `sesi`
- Sesi tanpa penjualan atau hadiah tetap muncul dengan nilai 0 (bukan `null`)
- **Anti N+1**: jumlah query endpoint `report`, `invoices`, dan `sessions` konstan (tidak naik saat jumlah sesi/invoice ditambah). Ukur dengan `DB::enableQueryLog()` pada data 3 sesi vs 15 sesi

## 6. Keamanan & Hardening Ringan
Periksa semuanya dan catat hasilnya. Perbaikan yang **diizinkan** hanya yang tercantum di sini:

| # | Periksa | Perbaikan bila gagal |
|---|---|---|
| S1 | State-changing request tanpa token CSRF → 419 | pastikan middleware stateful aktif |
| S2 | `/api/login` dibatasi percobaan | tambah `throttle:5,1` pada route login |
| S3 | Respons error API selalu JSON, tanpa stack trace saat `APP_DEBUG=false` | sudah ada `shouldReturnJson`; verifikasi |
| S4 | CORS: header `Access-Control-Allow-Origin: *` terlihat pada respons | batasi `allowed_origins` di `config/cors.php` ke origin aplikasi (`APP_URL`), `supports_credentials` sesuai kebutuhan SPA same-origin |
| S5 | Route uji sementara (mis. `/api/dev/token`) | hapus bila ada |
| S6 | Mass assignment: `$fillable` hanya kolom yang memang boleh diisi; `role`, `status`, `paid_at`, `total` tidak bisa diisi lewat body bebas pada endpoint yang tidak semestinya | rapikan `$fillable` dan validasi |
| S7 | XSS: nama peserta berisi `<script>alert(1)</script>` tampil sebagai teks di tabel, invoice, cetak, dan teks WhatsApp | escape/encode bila ada pemakaian `dangerouslySetInnerHTML` atau `innerHTML` |
| S8 | `.env`, `storage`, `vendor` tidak bisa diakses lewat browser (hanya folder `public`) | perbaiki dokumen root/konfigurasi server |
| S9 | Akun seeder `admin@example.com` / `password` | sediakan seeder yang mengambil email dan password dari `.env`, dan catat di laporan agar diganti sebelum dipakai nyata |
| S10 | `composer audit` dan `npm audit --omit=dev` | catat temuan; upgrade hanya untuk patch/minor yang tidak mengubah versi utama stack yang dikunci (React 17, antd 4.24, Laravel 10, Mix 6) |
| S11 | Sesi kedaluwarsa (401) di frontend | axios interceptor mengarahkan ke `/login` dengan pesan "Sesi berakhir, silakan login lagi" |
| S12 | Klik ganda tombol Simpan membuat invoice kembar | tombol `loading`/disabled selama request; pastikan tidak terbentuk dua invoice |

**Keterbatasan yang diketahui (catat, jangan diperbaiki di tahap ini):** semua user yang login memiliki akses penuh (belum ada pembedaan role).

## 7. Performa Dasar
- Seed data uji: 1 event, 15 sesi, 500 invoice (factory, hanya di environment testing/lokal)
- Periksa index; bila belum ada, tambahkan migration baru (tanpa mengubah tabel yang sudah ada): `invoices(event_id, status)`, `invoice_items(invoice_id)`, `invoice_items(event_session_id)`, `prizes(event_session_id)` (kecuali sudah tercakup index unik)
- Catat waktu respons `GET /events/{id}/invoices`, `/sessions`, dan `/report` pada data di atas. Target: di bawah 1 detik di lokal; catat bila lebih

## 8. Aturan Perbaikan Bug
- **P0** (data/uang salah, kuota jebol, login/akses bocor) dan **P1** (fitur utama tidak bisa dipakai): **wajib diperbaiki** di tahap ini, lalu tambahkan test yang mereproduksinya
- **P2** (tampilan/pesan salah, tidak memengaruhi data) dan **P3** (kosmetik): dicatat di laporan, perbaiki hanya bila perbaikannya satu atau dua baris
- Perbaikan tidak boleh mengubah kontrak API atau skema yang sudah dipakai frontend, kecuali migration index di bagian 7

## 9. Acceptance Criteria
- [ ] `php artisan test` hijau, memakai database `tiketlomba_test`, dan database dev tidak tersentuh
- [ ] Seluruh cakupan di bagian 5 ada testnya
- [ ] Bagian 6 (S1 sampai S12) terisi lengkap di laporan, dengan status Lolos, Diperbaiki, atau Dicatat
- [ ] Tidak ada N+1 pada `report`, `invoices`, `sessions` (dibuktikan test)
- [ ] Tidak ada bug P0/P1 yang tersisa
- [ ] Seluruh skenario Lampiran A yang bisa diuji lewat backend memberi angka yang sama dengan nilai harapan
- [ ] `docs/TEST-REPORT.md` dibuat sesuai format bagian 10
- [ ] Tidak ada library baru; fitur Tahap 1 sampai 4 tetap berjalan

## 10. Output yang Diharapkan dari Agent
`docs/TEST-REPORT.md` berisi:
1. Ringkasan: jumlah test, hasil lulus/gagal, waktu jalan
2. Tabel hasil bagian 5 (modul → jumlah test → status)
3. Tabel keamanan S1 sampai S12 (status dan tindakan)
4. Tabel performa (endpoint, jumlah query, waktu respons)
5. Daftar bug: ID, tingkat (P0 sampai P3), deskripsi, status (diperbaiki/dicatat), test pereproduksi
6. Risiko tersisa dan rekomendasi sebelum dipakai di acara nyata
7. Perintah untuk menjalankan ulang tes

---

# Lampiran A — Skenario Uji Manual (UAT) untuk Pemilik Proyek
Jalankan di browser (Chrome desktop) dan di HP (lebar ±375px). Centang tiap baris.

**Data uji.** Buat event **"Tes Event"** (kode invoice akan berawalan `TE-`), dengan dua sesi:
- **Sesi A**: harga Rp 100.000, kuota 3
- **Sesi B**: harga Rp 50.000, kuota 2

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Login dengan password salah, lalu benar | Pesan error, lalu masuk dashboard |
| 2 | Tambah peserta **Budi**: A×2 + B×1, status belum lunas | Total Rp 250.000, kode `TE-0001`, invoice terbuka |
| 3 | Tambah peserta **Sari**: A×1, status **lunas** | Total Rp 100.000, kode `TE-0002` |
| 4 | Lihat tab Sesi | A: terjual 3, sisa 0 · B: terjual 1, sisa 1 |
| 5 | Tambah peserta baru beli A×1 | **Ditolak** (sisa 0); sesi A di dropdown tidak bisa dipilih |
| 6 | Tambah peserta beli B×2 | **Ditolak** (sisa 1, pesan menyebut sisa 1) |
| 7 | Tab Laporan, bagian tabel | A: pemasukan Rp 100.000, belum lunas Rp 200.000 · B: pemasukan Rp 0, belum lunas Rp 50.000 |
| 8 | Isi hadiah pada tabel: Sesi A Juara 1 = 50.000, Juara 2 = 30.000; Sesi B kosong; Simpan | Total hadiah A Rp 80.000; laporan memuat hadiah setelah refresh |
| 9 | Lihat ringkasan | Pemasukan Rp 100.000 · Belum lunas Rp 250.000 · Hadiah Rp 80.000 · **Laba Rp 20.000** |
| 10 | Tandai invoice Budi **Lunas** | Muat ulang Laporan: pemasukan Rp 350.000, belum lunas Rp 0, laba Rp 270.000 |
| 11 | Edit Budi: A×2 menjadi A×1 | Total Rp 150.000; sesi A sisa 1; laporan ikut berubah |
| 12 | Ubah harga Sesi A menjadi Rp 120.000 | Invoice lama tidak berubah; pemasukan lama tetap |
| 13 | Hapus invoice Sari | Kuota A bertambah, kode `TE-0002` tidak dipakai ulang oleh invoice baru (`TE-0003`) |
| 14 | Coba hapus Sesi A dan hapus event "Tes Event" | Keduanya **ditolak** (sudah punya invoice) |
| 15 | Nama peserta `<b>coba</b>` | Tampil sebagai teks apa adanya di tabel, invoice, cetak |
| 16 | Klik **Simpan** cepat dua kali saat tambah peserta | Hanya satu invoice terbentuk |
| 17 | Salin untuk WhatsApp, lalu tempel di WhatsApp | Format sesuai, nama sesi benar |
| 18 | Cetak invoice (pratinjau cetak) | Hanya area invoice yang tampil |
| 19 | Biarkan sesi habis (atau hapus cookie) lalu klik sesuatu | Diarahkan ke login dengan pesan sesi berakhir |
| 20 | Buka dari HP lewat IP laptop, lakukan alur 2 sampai 9 | Tabel bisa digeser, nama sesi tetap terlihat, tombol mudah ditekan |

# Lampiran B — Perintah Berguna
```powershell
php artisan test
php artisan test --filter=InvoiceTest
composer audit
npm audit --omit=dev
```
