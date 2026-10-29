# Rancangan Pengujian Pra-Produksi — Sistem Tiket Lomba

Tujuan: menemukan bug dan celah **sebelum** aplikasi naik ke production (Docker + CI/CD). Dokumen ini melengkapi PRD Tahap 5 (tes otomatis dasar). Kolom **Siapa**: `A` = bisa dikerjakan agent (kode/tes), `K` = kamu (manual/operasional).

Urutan kerja yang disarankan: bagian 2 (risiko) → 8 (celah yang sudah terlihat, perbaiki dulu) → 4 (lapisan tes) → 5 (query integritas) → 9 (Go/No-Go).

---

## 1. Prinsip
1. **Uji uang dan kuota paling keras.** Kesalahan di sini langsung merugikan.
2. **Uji di lingkungan yang menyerupai production** (MySQL yang sama, Docker, HTTPS). Banyak bug baru muncul saat `APP_DEBUG=false`, cache aktif, atau banyak pengguna bersamaan.
3. **Satu temuan = satu test pereproduksi** (supaya tidak kembali).
4. **Data integritas diperiksa dengan query**, bukan hanya lewat layar (bagian 5).

## 2. Peta Risiko (urut paling berbahaya)
| # | Risiko | Dampak | Diuji di |
|---|---|---|---|
| R1 | Dua admin menjual tiket terakhir bersamaan (kuota jebol) | Tiket terjual melebihi kuota | C |
| R2 | Kode/nomor invoice kembar saat simpan bersamaan | Invoice ganda/bingung | C |
| R3 | Total, subtotal, pemasukan, atau laba salah hitung | Uang salah | A, B |
| R4 | Akses tanpa login ke data atau API | Kebocoran data | D |
| R5 | Stack trace, `.env`, atau kredensial bocor | Kompromi sistem | D, H |
| R6 | Login rusak di production (cookie, HTTPS, proxy, sesi) | Aplikasi tidak bisa dipakai | H |
| R7 | Data hilang saat redeploy atau tidak bisa dipulihkan | Data acara hilang | G, H |
| R8 | Migrasi gagal di data nyata | Downtime | G |
| R9 | Aplikasi lambat saat hari-H (banyak admin, banyak invoice) | Antrian di kasir | F |
| R10 | Pipeline lolos padahal tes gagal, atau rahasia terbawa ke image/log | Rilis cacat/bocor | I |

## 3. Lingkungan Uji
- **Staging** = `docker compose` yang sama dengan production: image aplikasi + MySQL (versi sama dengan production) + reverse proxy (nginx/Caddy) dengan **HTTPS**. Database terpisah dari development.
- Pakai `APP_ENV=production`, `APP_DEBUG=false`, `config:cache`, dan `route:cache` seperti production (banyak bug hanya muncul dengan setelan ini).
- Data uji: 1 event, 20 sesi, 1.000 invoice (factory/seeder khusus staging), plus event kecil "Tes Event" untuk skenario UAT.
- Akun uji: superadmin dan satu admin biasa (password kuat, bukan bawaan seeder).

---

## 4. Lapisan Pengujian

### A. Tes Otomatis Backend (PHPUnit, database MySQL `tiketlomba_test`)
Dasar sudah ada di PRD Tahap 5. Tambahan yang sering terlewat:
| ID | Skenario | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| A1 | Beli tepat sebesar sisa, lalu satu tiket lagi | Pertama sukses, kedua 422 | A |
| A2 | Edit invoice: ubah qty naik dan turun, ganti sesi, tambah/hapus item | Kuota, total, laporan tepat tanpa hitung ganda | A |
| A3 | Hapus invoice, lalu buat baru | Kuota kembali; nomor invoice **tidak dipakai ulang** | A |
| A4 | Ubah harga sesi setelah transaksi | Invoice lama dan pemasukan lama tidak berubah | A |
| A5 | Ubah status bolak-balik berkali-kali | `paid_at` konsisten; Rekap Peserta dan Laporan ikut berubah | A |
| A6 | Matriks otorisasi: **semua** route `/api/*` tanpa login | 401 JSON (bukan 302, 500, atau HTML) | A |
| A7 | Mass assignment: kirim `total`, `status`, `paid_at`, `event_id`, `created_by`, `nomor`, `kode` lewat body yang tidak semestinya | Diabaikan oleh server | A |
| A8 | Validasi batas: qty 0/101, nominal 0/negatif/10 digit, nama 150 vs 151 karakter, tanggal tidak valid | 422 dengan pesan benar | A |
| A9 | Template invoice: placeholder tak dikenal, `{total}` di nama pemesan, bagian nonaktif, emoji | Render sesuai aturan, tanpa penggantian ganda | A |
| A10 | Nomor urut sesi: nomor kembar, reorder tukar nomor, hapus sesi | Sesuai PRD; urutan konsisten di semua endpoint | A |
| A11 | Event dengan invoice/pengeluaran dihapus; sesi dengan invoice dihapus | Ditolak 422 | A |
| A12 | Soft delete pengeluaran | Tidak tampil dan tidak dihitung; baris masih di database | A |
| A13 | Hitung N+1: jumlah query konstan pada `report`, `invoices`, `sessions`, `rekap-peserta`, `bookkeeping` (3 vs 15 sesi) | Konstan | A |
| A14 | Konsistensi silang: `ringkasan.pemasukan` Laporan = `pemasukan` Pengeluaran = SUM query manual | Sama persis | A |
| A15 | `migrate:fresh --seed`, `migrate:rollback`, lalu `migrate` lagi | Tanpa error | A |

### B. Pemeriksaan Integritas Data
Jalankan query di bagian 5 pada staging setelah semua skenario, dan secara berkala di production. Hasil yang benar: **semua query mengembalikan 0 baris**. Ditulis sebagai test PHPUnit (`tests/Feature/DataIntegrityTest.php`) agar CI ikut menjalankannya. `A`

### C. Tes Konkurensi (race condition)
PHPUnit berjalan berurutan, jadi butuh skrip terpisah (di luar kode aplikasi, mis. `tests/Race/race.php` memakai `curl_multi`, **harus berjalan di MySQL**, karena `lockForUpdate` tidak berfungsi di SQLite).
| ID | Skenario | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| C1 | Login sekali, lalu 10 request `POST invoice` bersamaan untuk sesi yang sisa 1 | Tepat 1 sukses (201), 9 gagal (422); `terjual ≤ kuota` | A |
| C2 | 20 request buat invoice bersamaan di event yang sama (sesi berbeda) | Semua sukses; `nomor` dan `kode` unik, tanpa lubang atau kembar | A |
| C3 | Edit invoice yang sama dari dua request bersamaan | Tidak ada kuota ganda atau total rusak (query integritas bersih) | A |
| C4 | Tandai lunas dua kali bersamaan | Status lunas, `paid_at` satu nilai | A |
| C5 | Dua admin di dua HP: satu menjual tiket terakhir, satunya membuka form | Yang kedua mendapat pesan kuota habis, bukan error 500 | K |
| C6 | Klik ganda tombol Simpan (peserta, pengeluaran, hadiah, template) | Tidak ada data kembar | K |

### D. Keamanan
| ID | Skenario | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| D1 | State-changing request tanpa token CSRF | 419 | A |
| D2 | Salah login berulang | Dibatasi (throttle) setelah 5 kali per menit | A |
| D3 | Payload XSS di nama peserta, keterangan pengeluaran, nama sesi/event, template: `<script>alert(1)</script>`, `"><img src=x onerror=alert(1)>` | Tampil sebagai teks di semua halaman, cetak, dan salinan | K |
| D4 | SQL injection pada `q` (pencarian) dan semua input teks: `' OR 1=1 --`, `'; DROP TABLE x;--` | Tidak merusak query, tidak membocorkan data | A |
| D5 | Akses `/.env`, `/.git/config`, `/storage/logs/laravel.log`, `/vendor/`, `/composer.json` lewat browser | 404/403 | K |
| D6 | Error saat `APP_DEBUG=false` (matikan DB, kirim JSON rusak) | Respons JSON/halaman generik, **tanpa stack trace** | K |
| D7 | Header respons | Tidak ada `X-Powered-By`; ada `X-Content-Type-Options`, `X-Frame-Options`, HSTS (di proxy) | K |
| D8 | Cookie sesi di HTTPS | `HttpOnly`, `Secure`, `SameSite=Lax` | K |
| D9 | CORS: request dari origin lain | Ditolak (bukan `Access-Control-Allow-Origin: *`) | K |
| D10 | Session fixation: ID sesi berubah setelah login; sesi dibuang setelah logout | Ya | A |
| D11 | Akun bawaan seeder (`admin@example.com` / `password`) | Tidak ada di production | K |
| D12 | `composer audit`, `npm audit --omit=dev` | Tanpa temuan High/Critical yang belum ditangani | A |
| D13 | Pindai rahasia di repository dan riwayat git (mis. `gitleaks`), pindai image Docker (mis. `trivy`) | Tanpa rahasia dan tanpa kerentanan Critical | K/A |
| D14 | Semua user login punya akses penuh | **Keterbatasan diketahui**: putuskan diterima atau tambah role sebelum multi-admin | K |

### E. Pengujian Fungsional Manual (UAT) dan Eksplorasi
Skenario berangka ada di Lampiran A PRD Tahap 5 dan Lampiran PRD Tahap 8. Tambahan:
| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| E1 | **Simulasi hari-H**: dua admin di dua HP, 30 pesanan nyata (sebagian lunas, sebagian belum, ada yang beli 2 sesi), edit dan hapus beberapa | Semua query integritas bersih; Rekap, Laporan, dan Pengeluaran cocok dengan hitungan manual |
| E2 | Rekap Peserta di layar besar/TV (mode layar penuh), 5 tabel menyamping | Terbaca, tidak ada scroll horizontal |
| E3 | Salin invoice, tempel di WhatsApp (HP) | Susunan, emoji, dan garis utuh; nama sesi benar; status benar |
| E4 | Ubah template setelah ada invoice | Perilaku sesuai keputusan (teks dibuat ulang dari template terbaru); semua invoice lama konsisten |
| E5 | Sesi habis di tengah pekerjaan (hapus cookie) | Diarahkan ke login dengan pesan jelas, isian tidak hilang diam-diam |
| E6 | Koneksi lambat (DevTools: Slow 3G) dan offline di tengah simpan | Ada indikator loading dan pesan error; tidak ada data ganda saat dicoba ulang |
| E7 | Browser: Chrome Android, Safari iOS, Chrome/Edge desktop; lebar 375px, 768px, 1366px, 1920px | Tampilan dan fungsi normal |
| E8 | Nama peserta panjang, emoji, huruf non-Latin, spasi di ujung, nama kembar dua orang | Tidak merusak tampilan; data benar |
| E9 | Event dengan 0 sesi, sesi tanpa penjualan, sesi tanpa hadiah, event tanpa invoice | Semua halaman tampil kosong dengan wajar (tanpa error) |
| E10 | Kuota 1 dan kuota 1000; harga 0 dan harga sangat besar | Perhitungan dan tampilan benar |

### F. Performa dan Beban
| ID | Skenario | Target | Siapa |
|---|---|---|---|
| F1 | 1 event, 20 sesi, 1.000 invoice: waktu `invoices`, `sessions`, `report`, `rekap-peserta`, `bookkeeping` | p95 < 1 detik | A |
| F2 | 20 pengguna bersamaan membuka daftar dan membuat invoice (mis. `k6`/`hey`/`ab`, alat eksternal, bukan bagian aplikasi) | Tanpa error 5xx; p95 < 2 detik | K |
| F3 | `EXPLAIN` pada query laporan dan rekap; pastikan memakai index | Tanpa full scan pada tabel besar | A |
| F4 | Slow query log MySQL aktif selama F2 | Tidak ada query > 1 detik | K |
| F5 | Ukuran bundle JS (`npm run prod`) | Dicatat; target < 2 MB total | A |

### G. Data, Migrasi, dan Pemulihan
| ID | Skenario | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| G1 | Jalankan semua migrasi pada **salinan data nyata** (dump development) | Sukses, data utuh, query integritas bersih | K |
| G2 | **Latihan backup dan restore**: `mysqldump` dari staging, restore ke database kosong, jalankan aplikasi | Aplikasi normal, data sama | K |
| G3 | Rollback migrasi terakhir lalu migrasi ulang | Tanpa kehilangan data pada kolom lain | A |
| G4 | Karakter khusus: emoji dan `━` tersimpan (`utf8mb4`) | Utuh setelah simpan dan baca | A |
| G5 | Zona waktu: `created_at`, `paid_at`, tanggal event, dan `{tanggal_invoice}` di sekitar tengah malam WIB | Tanggal benar menurut WIB; urutan lunas tetap benar | A |

### H. Docker dan Deploy
| ID | Skenario | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| H1 | Build image dari repo bersih (tanpa cache) lalu jalankan | Berjalan tanpa langkah manual tersembunyi | K |
| H2 | `php artisan route:cache`, `config:cache`, `view:cache` pada image | Sukses (lihat celah G1 di bagian 8: route berbentuk closure membuat `route:cache` gagal) | A |
| H3 | `env()` hanya dipakai di file `config/*`; setelah `config:cache` semua setelan masih terbaca | Ya | A |
| H4 | Container restart / redeploy | **Login dan sesi tidak hilang acak; data MySQL tetap** (volume terpisah, sesi tidak di file lokal container) | K |
| H5 | Healthcheck endpoint (`GET /up` atau sejenis) dan `HEALTHCHECK` Docker | Container sehat/tidak sehat terdeteksi | A |
| H6 | Di belakang reverse proxy HTTPS: URL, redirect, dan cookie benar (`TrustProxies`, `APP_URL`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` = domain asli) | Login berhasil di domain production | K |
| H7 | Izin folder `storage` dan `bootstrap/cache`; user non-root di container | Tulis log tanpa error, tidak berjalan sebagai root | K |
| H8 | Log ke stdout/stderr (`LOG_CHANNEL=stderr`), tidak menumpuk di dalam container | Terbaca lewat `docker logs` | K |
| H9 | Variabel lingkungan: `APP_KEY` baru, kredensial DB khusus (bukan root, bukan password kosong), tidak ada rahasia di image atau repo | Ya | K |
| H10 | Jalankan migrasi sebagai **langkah rilis terpisah**, bukan otomatis tiap container start | Rilis dapat diulang dan aman | K |
| H11 | Asset hasil `npm run prod` (nama berversi di `mix-manifest.json`) ikut dalam image; cache browser tidak memakai versi lama setelah rilis | Halaman memuat versi terbaru | K |
| H12 | Rencana **rollback**: image sebelumnya tetap tersimpan; latihan kembali ke versi lama | Bisa kembali < 5 menit | K |

### I. CI/CD
Tahap pipeline yang disarankan: `lint` → `phpunit (service MySQL)` → `npm ci && npm run prod` → `audit` → `docker build` → `scan image` → `deploy staging` → `smoke test` → **persetujuan manual** → `deploy production` → `smoke test`.
| ID | Pengujian pipeline | Hasil yang diharapkan | Siapa |
|---|---|---|---|
| I1 | Sengaja gagalkan satu test, push | Pipeline **berhenti**, tidak ada deploy | K |
| I2 | Test dijalankan di MySQL service, bukan SQLite | Ya (agar `lockForUpdate` dan constraint nyata) | A |
| I3 | Rahasia hanya dari secret store CI; tidak tercetak di log | Tidak ada nilai rahasia di log | K |
| I4 | Deploy production wajib persetujuan manual dan hanya dari branch utama | Ya | K |
| I5 | Smoke test pasca-deploy (hanya baca): login, `GET /api/events`, `GET /up`, halaman `/admin` memuat | Gagal = rollback otomatis atau peringatan | A/K |
| I6 | Deploy dua kali berturut-turut | Idempoten, tanpa error migrasi | K |
| I7 | Cache dependensi (`composer`, `npm`) dan waktu build | < 10 menit | K |

### J. Ketahanan terhadap Kegagalan
| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| J1 | Matikan MySQL saat aplikasi berjalan | Pesan error rapi (JSON), tidak ada stack trace; pulih sendiri saat MySQL hidup |
| J2 | Matikan container di tengah simpan invoice | Tidak ada invoice setengah jadi (transaction); query integritas bersih |
| J3 | Disk penuh / folder log tidak bisa ditulis | Aplikasi tidak crash; error tercatat |
| J4 | Request sangat besar atau JSON rusak | 4xx rapi, bukan 500 |

---

## 5. Query Pemeriksaan Integritas (hasil benar = 0 baris)
```sql
-- 1. Kuota jebol
SELECT s.id, s.nama, s.kuota, COALESCE(SUM(ii.qty),0) AS terjual
FROM event_sessions s LEFT JOIN invoice_items ii ON ii.event_session_id = s.id
GROUP BY s.id, s.nama, s.kuota HAVING terjual > s.kuota;

-- 2. Total invoice tidak sama dengan jumlah subtotal item
SELECT i.id, i.total, SUM(ii.subtotal) AS dihitung
FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id
GROUP BY i.id, i.total HAVING i.total <> dihitung;

-- 3. Invoice tanpa item
SELECT i.id FROM invoices i LEFT JOIN invoice_items ii ON ii.invoice_id = i.id WHERE ii.id IS NULL;

-- 4. Subtotal item salah
SELECT id FROM invoice_items WHERE subtotal <> qty * harga;

-- 5. Status dan paid_at tidak sinkron
SELECT id FROM invoices
WHERE (status = 'lunas' AND paid_at IS NULL) OR (status = 'belum_lunas' AND paid_at IS NOT NULL);

-- 6. Kode invoice tidak sesuai format atau tidak cocok dengan nomor
SELECT id, kode, nomor FROM invoices
WHERE kode NOT REGEXP '^[A-Z0-9]{1,4}-[0-9]{4,}$'
   OR CAST(SUBSTRING_INDEX(kode, '-', -1) AS UNSIGNED) <> nomor;

-- 7. Item menunjuk sesi dari event lain
SELECT ii.id FROM invoice_items ii
JOIN invoices i ON i.id = ii.invoice_id
JOIN event_sessions s ON s.id = ii.event_session_id
WHERE s.event_id <> i.event_id;

-- 8. Hadiah di luar aturan
SELECT id FROM prizes WHERE juara NOT BETWEEN 1 AND 5 OR nominal < 1;

-- 9. Pengeluaran tidak valid (yang belum dihapus)
SELECT id FROM expenses WHERE deleted_at IS NULL AND nominal < 1;

-- 10. Pemasukan per event (bandingkan dengan angka di Laporan dan Pengeluaran)
SELECT i.event_id, SUM(ii.subtotal) AS pemasukan_lunas
FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id
WHERE i.status = 'lunas' GROUP BY i.event_id;
```

## 6. Data "Nakal" untuk Input Teks dan Angka
| Jenis | Contoh |
|---|---|
| XSS | `<script>alert(1)</script>`, `"><img src=x onerror=alert(1)>` |
| SQL | `' OR 1=1 --`, `'; DROP TABLE invoices;--` |
| Placeholder | `{total}`, `{nama_pemesan}`, `{status}` |
| Spasi/kosong | `   `, nama diawali/diakhiri spasi, baris baru di dalam nama |
| Unicode | emoji `🔥`, huruf Arab/Jepang, karakter lebar nol, `━━━` |
| Panjang | 150 dan 151 karakter, 2.000 dan 2.001 karakter (template) |
| Angka | `0`, `-1`, `1.5`, `"abc"`, `999999999`, `1000000000`, `1e9` |
| Tipe | string untuk angka, array untuk string, `null` untuk wajib, field ekstra |
| Tanggal | `2026-02-30`, `0000-00-00`, tanggal jauh di masa depan |

## 7. Sesi Eksplorasi (30 menit per sesi, catat semua keanehan)
1. **"Jadi kasir yang ceroboh"**: salah ketik, batalkan di tengah, tutup drawer, kembali, tekan tombol Back browser.
2. **"Jadi dua admin yang bentrok"**: dua HP mengedit data yang sama.
3. **"Jadi pengguna jahil"**: ubah URL (`/admin/events/9999`), buka halaman tanpa login, panggil API langsung dari Postman dengan data aneh.
4. **"Jadi auditor"**: cocokkan semua angka (Laporan, Pengeluaran, Rekap) dengan hitungan manual di Excel.
5. **"Jadi hari-H"**: jalankan alur penuh sambil bergantian antar HP dan layar besar, dengan sinyal buruk.

## 8. Celah yang Sudah Terlihat dari Review (perbaiki sebelum tes lain)
| # | Temuan | Tindakan |
|---|---|---|
| G1 | Route berbentuk closure (mis. `/admin/{any?}` memakai `fn () => view(...)`) membuat `php artisan route:cache` **gagal**, padahal production umumnya memakai route cache | Ganti dengan `Route::view('/admin/{any?}', 'admin.app')->where('any', '.*')`; hapus route closure lain |
| G2 | `.env` lokal memakai `APP_DEBUG=true`; respons error sempat menampilkan **stack trace HTML** | Production wajib `APP_DEBUG=false`; uji D6 |
| G3 | Header respons memuat `Access-Control-Allow-Origin: *` | Batasi `config/cors.php` ke domain aplikasi |
| G4 | `SESSION_DRIVER=file`: di Docker sesi hilang saat container diganti dan tidak jalan di banyak replika | Pakai `database` atau `redis`, atau volume tetap bila satu container |
| G5 | Di belakang reverse proxy HTTPS tanpa `TrustProxies` yang benar, Laravel mengira request HTTP, sehingga cookie `Secure`, URL, dan redirect salah | Atur `TrustProxies`, `APP_URL`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` |
| G6 | `APP_KEY`, host, dan kredensial database lokal pernah ditempel di obrolan; DB lokal memakai user `root` tanpa password | **Buat `APP_KEY` baru untuk production** (`php artisan key:generate --show`), buat user database khusus dengan hak minimum |
| G7 | Akun seeder `admin@example.com` / `password` | Ganti seeder agar membaca dari environment, dan buat akun production manual |
| G8 | Tidak ada endpoint healthcheck | Tambah `GET /up` ringan (tanpa data sensitif) untuk Docker dan smoke test |
| G9 | Semua user login berakses penuh (tanpa role) | Putuskan: diterima untuk tahap awal atau tambahkan role sebelum ada lebih dari satu admin yang dipercaya sama |
| G10 | Teks invoice dirender dari template **terbaru**, bukan snapshot: mengubah template mengubah invoice lama | Pastikan ini memang yang diinginkan; bila tidak, simpan snapshot teks saat invoice dikirim |
| G11 | Pembatasan percobaan login belum tentu aktif | Pastikan `throttle` pada route login (uji D2) |

## 9. Kriteria Go / No-Go ke Production
**No-Go bila salah satu terjadi:**
- Ada bug **P0/P1** terbuka (uang/kuota/akses salah, login rusak)
- Query integritas (bagian 5) mengembalikan baris setelah simulasi hari-H
- Tes konkurensi C1 atau C2 gagal
- `APP_DEBUG` masih `true`, stack trace bocor, `.env` atau `.git` dapat diakses
- Backup belum pernah **di-restore** dengan sukses (G2) atau rollback belum dilatih (H12)
- Pipeline tidak berhenti saat tes gagal (I1)

**Go bila:**
- [ ] `php artisan test` hijau di CI (MySQL) dan semua test pereproduksi bug sudah ada
- [ ] Bagian 8 (G1 sampai G11) semuanya Selesai atau Diterima secara sadar
- [ ] Lapisan A sampai J dijalankan; temuan P0/P1 nol; P2/P3 tercatat
- [ ] UAT simulasi hari-H (E1) lolos oleh minimal dua orang
- [ ] Staging berjalan stabil minimal 3 hari tanpa error baru di log
- [ ] Rencana rilis tertulis: urutan langkah, siapa, kapan, dan cara rollback

## 10. Format Laporan Temuan
| ID | Lapisan | Tingkat (P0 sampai P3) | Langkah reproduksi | Hasil nyata | Hasil harapan | Status | Test pereproduksi |
|---|---|---|---|---|---|---|---|
- **P0**: uang/kuota salah, data hilang, kebocoran akses, tidak bisa login
- **P1**: fitur utama tidak bisa dipakai
- **P2**: tampilan/pesan salah, data tidak terpengaruh
- **P3**: kosmetik
