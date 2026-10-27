# PRD Tahap 8 — Template Invoice Berbagian

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 8 di atas hasil Tahap 1 sampai 7. Jangan ubah yang sudah berjalan kecuali disebut di sini. Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 sampai 7 selesai (termasuk peserta dan invoice dengan kode `INISIAL-0001`, status lunas/belum lunas, dan tes otomatis). Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 2. Tujuan
Invoice berupa **teks siap kirim (WhatsApp)** yang tersusun dari **8 bagian berurutan**, dipisahkan garis. Admin dapat mengubah isi teks tiap bagian per event. Bagian yang bersifat data (nama pemesan, kelas pesanan, total) terisi otomatis dari pesanan yang dibuat di menu Peserta & Invoice.

**Urutan bagian (tetap, dari atas):**
1. Salam pembuka
2. Nama event
3. Nama pemesan dan no urut invoice
4. Kelas yang dipesan (**otomatis**: nama sesi + nominal dari sesi yang dipilih saat buat pesanan)
5. Total pembayaran
6. Ketentuan pembayaran
7. Penutup
8. Advertisement

## 3. Scope Tahap 8
1. Tabel dan API template invoice per event
2. Mesin render teks invoice (satu implementasi di backend)
3. **Perubahan isi menu Pengaturan Invoice yang sudah ada** (tombol dan komponen `InvoiceTemplateModal` dipakai ulang, bukan menu baru): isinya menjadi 8 bagian berurutan dengan placeholder dan pratinjau langsung
4. Tampilan invoice (`InvoiceView`) memakai teks hasil template, dengan tombol Salin dan Cetak
5. Isi lama Pengaturan Invoice (judul dan catatan) dari Tahap 3 diganti oleh 8 bagian di atas

## 4. Di Luar Scope
Banyak template per event, riwayat/versi template, editor WYSIWYG, render tebal/miring (tanda `*...*` ditampilkan apa adanya), kirim otomatis ke WhatsApp, PDF, logo atau gambar, mode "tampilkan semua sesi dengan tanda yang dipesan", template per sesi, menyalin template dari event lain, serta menu, tombol, atau halaman baru untuk pengaturan (gunakan menu **Pengaturan Invoice** yang sudah ada).

## 5. Database
**invoice_templates** (model `InvoiceTemplate`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| event_id | FK → events.id | **unik**, `cascadeOnDelete` |
| pemisah | string(50) | default `━━━━━━━━━━━━━━━━━━` |
| bagian | json | struktur di bawah |
| timestamps | | |

Pastikan koneksi MySQL memakai `utf8mb4` agar emoji dan karakter `━` tersimpan utuh.

**Struktur `bagian`** (tepat 8 kunci, urutan ini):
```json
{
  "salam_pembuka":        { "aktif": true,  "teks": "..." },
  "nama_event":           { "aktif": true,  "teks": "..." },
  "pemesan":              { "aktif": true,  "teks": "..." },
  "kelas_pesanan":        { "aktif": true,  "teks": "🎟️ KELAS PESANAN", "format_baris": "{no}. {nama_sesi} — {harga} = {qty}" },
  "total_pembayaran":     { "aktif": true,  "teks": "..." },
  "ketentuan_pembayaran": { "aktif": true,  "teks": "..." },
  "penutup":              { "aktif": true,  "teks": "..." },
  "iklan":                { "aktif": false, "teks": "" }
}
```
**Template bawaan** (dipakai bila event belum punya baris template) disimpan di `config/invoice_template.php`, isinya teks generik yang memakai placeholder (tanpa data pribadi), `iklan` nonaktif.

**Kolom lama** `events.invoice_judul` dan `events.invoice_catatan` (Tahap 3): **jangan dihapus dari database**, tetapi hentikan pemakaiannya di kode.

## 6. Placeholder
Format `{nama}` (huruf kecil dan garis bawah). Hanya placeholder di bawah ini yang diganti; teks lain dalam kurung kurawal dibiarkan apa adanya.

**Umum (semua bagian)**
| placeholder | hasil |
|---|---|
| `{nama_event}` / `{nama_event_besar}` | nama event / HURUF BESAR |
| `{tanggal_event}` / `{tanggal_event_besar}` | `Minggu, 11 Oktober 2026` / `MINGGU, 11 OKTOBER 2026` (bahasa Indonesia) |
| `{lokasi_event}` | lokasi event (kosong bila tidak diisi) |
| `{nama_pemesan}` | `nama_peserta` invoice |
| `{nomor_urut}` | `nomor` invoice tanpa nol di depan (contoh `277`) |
| `{kode_invoice}` | contoh `SHC-0277` |
| `{tanggal_invoice}` | tanggal dibuat, zona Asia/Jakarta, contoh `11 Oktober 2026` |
| `{total}` / `{total_angka}` | `Rp4.500.000` / `4.500.000` |
| `{status}` | `LUNAS` atau `BELUM LUNAS` |

**Khusus `format_baris` bagian Kelas Pesanan (satu baris per sesi yang dipesan)**
| placeholder | hasil |
|---|---|
| `{no}` | nomor urut baris (1, 2, …) |
| `{nama_sesi}` | nama sesi |
| `{harga}` / `{harga_angka}` | harga satuan snapshot, `Rp550.000` / `550.000` |
| `{qty}` | jumlah tiket sesi itu |
| `{subtotal}` / `{subtotal_angka}` | qty × harga, `Rp1.100.000` / `1.100.000` |

## 7. Aturan Render (WAJIB, satu implementasi di backend)
Buat class `App\Services\InvoiceRenderer` (murni, mudah dites). Aturan:
1. Bagian diproses **sesuai urutan tetap** di bagian 2. Bagian dilewati bila `aktif = false` atau `teks` kosong setelah `trim` (bagian Kelas Pesanan tidak pernah kosong bila ada item).
2. **Bagian Kelas Pesanan** = `teks` (judul) + baris-baris item. Item diambil dari item invoice **berurutan menurut `event_session_id` naik**, tiap item satu baris dari `format_baris` (`{no}` mulai 1). Hanya sesi yang dipesan pada invoice itu.
3. Bagian-bagian digabung dengan `"\n" + pemisah + "\n"`. Di dalam satu bagian, baris dan paragraf (baris kosong) dipertahankan apa adanya. Akhir baris dinormalkan menjadi `\n`; spasi di ujung tiap baris dibuang.
4. Penggantian placeholder dilakukan **satu kali lewat** (`preg_replace_callback`). Nilai yang disisipkan **tidak** diproses ulang, sehingga nama pemesan seperti `{total}` tampil apa adanya dan tidak memicu penggantian.
5. Uang: `Rp` + angka dengan titik ribuan, tanpa spasi (`Rp4.500.000`). Varian `_angka` hanya angka.
6. Hasil berupa **teks polos** (tanpa HTML). Tanda `*...*` ditampilkan apa adanya.
7. Teks selalu dibuat ulang dari template **saat ini** setiap kali dibuka (bukan snapshot); mengubah template mengubah tampilan semua invoice event itu.

## 8. Routes & API
Di dalam grup `auth:sanctum`:
- `GET /api/events/{event}/invoice-template` → `{ "pemisah", "bagian", "is_default": bool, "placeholder": { "umum": [...], "baris": [...] } }`. Bila belum ada baris, kembalikan template bawaan dengan `is_default: true`
- `PUT /api/events/{event}/invoice-template` → simpan (upsert), respons sama dengan GET
- `POST /api/events/{event}/invoice-template/preview` → body sama dengan PUT (tanpa menyimpan) → `{ "teks": "..." }`, dirender dengan **data contoh**: nama pemesan `Rizal Medan`, nomor urut `277`, item = dua sesi pertama event (qty 1) atau sesi contoh bila belum ada sesi
- `GET /api/invoices/{invoice}/teks` → `{ "teks": "..." }` untuk invoice itu
- Endpoint `PUT /api/events/{event}/invoice-template` dari Tahap 3 (judul dan catatan) **diganti** oleh versi baru di atas (URL sama, isi body baru); komponen `InvoiceTemplateModal.js` yang sudah ada **diubah**, bukan dibuat duplikat

**Validasi (PUT dan preview):** `pemisah` required|string|max:50; `bagian` required|array dengan **tepat** 8 kunci di atas (kunci lain atau kurang → 422); tiap `aktif` required|boolean; `teks` nullable|string|max:2000; `bagian.kelas_pesanan.format_baris` required|string|max:255. Karakter emoji harus lolos. Error format standar Laravel (422).

## 9. Kebutuhan Frontend
**Struktur**
```
resources/js/client/admin/components/invoice/
├── InvoiceTemplateModal.js     # komponen yang SUDAH ADA, diubah isinya
├── TemplateSectionEditor.js    # editor satu bagian
└── PlaceholderHelp.js          # daftar placeholder
```
Tombol **Pengaturan Invoice** yang sudah ada di tab Peserta & Invoice **dipakai apa adanya** dan tetap membuka `InvoiceTemplateModal` (tidak ada menu, tombol, atau halaman baru). Yang berubah hanya isi modal. Redux tetap hanya untuk auth.

**InvoiceTemplateModal (ubah komponen yang sudah ada)**
- `Modal` lebar 960px (di mobile `width="100%"` dan `style={{ top: 0 }}`). Desktop: dua kolom (editor kiri, **Pratinjau** kanan). Mobile: editor di atas, pratinjau di bawah
- Atas: `Input` **Garis pemisah**
- Editor: `Collapse` dengan 8 panel **sesuai urutan**, judul panel `1. Salam Pembuka`, `2. Nama Event`, `3. Nama Pemesan & No Urut`, `4. Kelas yang Dipesan`, `5. Total Pembayaran`, `6. Ketentuan Pembayaran`, `7. Penutup`, `8. Advertisement`. Tiap panel punya `Switch` Aktif di header dan `Input.TextArea` (`autoSize={{ minRows: 3, maxRows: 12 }}`, `maxLength=2000`, `showCount`). Panel 4 menambah `Input` **Format baris** di bawah TextArea judul, dengan keterangan "Dipakai untuk tiap sesi yang dipesan"
- **PlaceholderHelp**: deretan `Tag` placeholder; **klik Tag menyisipkan placeholder pada posisi kursor** di kolom teks yang terakhir difokuskan (placeholder khusus baris hanya aktif untuk kolom Format baris)
- **Pratinjau**: memanggil `POST .../preview` dengan isi editor saat ini (debounce 500 ms) dan menampilkan `teks` dalam `<pre>` (`white-space: pre-wrap`, font 13px, latar putih, border, tinggi maks 70vh dengan scroll)
- Tombol: **Simpan Template** (loading, mencegah klik ganda), **Reset ke Bawaan** (`Popconfirm`, mengisi editor dengan template bawaan tanpa menyimpan), **Batal**. `Tag` kuning "Ada perubahan belum disimpan" selama isian berbeda dari data server
- Sukses simpan → `message.success`; 422 tampil di `message.error` dan isian tidak hilang

**InvoiceView (ubah dari Tahap 3)**
- Saat dibuka, memanggil `GET /api/invoices/{id}/teks`; menampilkan teks dalam `<pre>` yang sama gayanya (`white-space: pre-wrap`)
- Tombol **Salin Teks** (`navigator.clipboard.writeText`, lalu `message.success("Tersalin")`) menggantikan tombol "Salin untuk WhatsApp" lama; tombol **Cetak** mencetak hanya area teks (CSS `@media print`)
- Setelah **Buat Peserta** sukses, InvoiceView otomatis terbuka dengan teks terbaru
- Tampilan tabel item lama pada InvoiceView dihapus (kontennya sudah ada dalam teks); status lunas/belum lunas tetap ditampilkan sebagai `Tag` di judul modal

**UX:** mobile-first (375px), teks UI berbahasa Indonesia, teks invoice selalu ditampilkan sebagai teks biasa (tanpa `dangerouslySetInnerHTML`).

## 10. Tes Otomatis (pola Tahap 5)
Tambahkan `tests/Unit/InvoiceRendererTest.php` dan `tests/Feature/InvoiceTemplateTest.php` (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`); `php artisan test` tetap hijau. Cakupan minimal:
- Urutan bagian benar; bagian nonaktif atau kosong dilewati bersama garis pemisahnya (tidak ada garis ganda)
- Pemisah muncul tepat `jumlah bagian tampil − 1` kali
- Placeholder umum terganti benar (nama event huruf besar, tanggal bahasa Indonesia, kode, nomor urut tanpa nol di depan, total, status)
- Bagian kelas: dua sesi → dua baris bernomor 1 dan 2, qty dan subtotal benar, **hanya sesi yang dipesan**, urut `event_session_id` naik
- Placeholder khusus baris tidak terganti di luar `format_baris`; placeholder tak dikenal dibiarkan
- **Satu kali lewat:** `nama_pemesan` = `{total}` tampil apa adanya
- Emoji dan `━` tersimpan dan terbaca utuh dari database
- Tanpa baris template → GET mengembalikan bawaan dengan `is_default: true`; PUT menyimpan; PUT kedua memperbarui (satu baris per event)
- Validasi: kunci bagian kurang atau lebih, `teks` > 2000, `aktif` bukan boolean → 422
- `preview` tidak menyimpan apa pun ke database
- `GET /invoices/{id}/teks` mengikuti template terbaru setelah template diubah
- Semua endpoint baru tanpa login → 401 JSON; tidak ada N+1 pada render invoice dengan banyak item

## 11. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `invoice_templates` ada dengan `event_id` unik; kolom lama di `events` tidak dihapus
- [ ] Tombol Pengaturan Invoice yang sudah ada membuka modal yang sama, kini berisi 8 panel berurutan, pemisah, dan pratinjau (tidak ada menu atau tombol baru)
- [ ] Mengubah teks di panel tampil di pratinjau dalam ±1 detik tanpa menyimpan
- [ ] Klik Tag placeholder menyisipkan teks di posisi kursor
- [ ] Simpan berhasil; refresh halaman memuat template yang sama, emoji utuh
- [ ] **Uji output** (Lampiran A): hasil render sama persis dengan contoh yang diharapkan
- [ ] Hanya sesi yang dipesan muncul di bagian Kelas Pesanan, dengan nominal sesuai harga snapshot; invoice dengan 2 sesi menampilkan 2 baris bernomor
- [ ] Mematikan Switch sebuah bagian menghilangkan bagian itu beserta garis pemisahnya
- [ ] Nama pemesan berisi `{total}` atau `<b>x</b>` tampil apa adanya
- [ ] Buat peserta baru: InvoiceView langsung menampilkan teks sesuai template; Salin Teks menempel di WhatsApp dengan susunan sama; Cetak hanya mencetak teks
- [ ] Mengubah template mengubah tampilan invoice yang sudah ada milik event itu; event lain tidak terpengaruh
- [ ] Reset ke Bawaan hanya mengubah editor sampai disimpan
- [ ] Tag "Ada perubahan belum disimpan" muncul dan hilang sesuai kondisi; klik Simpan dua kali tidak menyebabkan error
- [ ] Endpoint tanpa login → 401; tidak ada error di Console; nyaman di lebar 375px
- [ ] `php artisan test` hijau; tidak ada library baru; Tahap 1 sampai 7 tetap berjalan

## 12. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, config, model, `InvoiceRenderer`, controller, route, komponen React, test)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `php artisan config:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

---

# Lampiran A — Data Uji dan Hasil yang Diharapkan

**Data:** event "1st Anniversary Solo High Class" (kode inisial `SHC`), tanggal 2026-10-11, lokasi "Solo High Class Stadium". Invoice nomor 277 atas nama **Rizal Medan**, status belum lunas, berisi satu item: sesi **MB A PAKET ANNIVERSARY**, harga Rp4.500.000, qty 1. Dua sesi lain di event itu tidak dipesan. `pemisah` = `━━━━━━━━━━━━━━━━━━`.

**Template uji (semua bagian aktif):**
| bagian | teks |
|---|---|
| salam_pembuka | `Assalamu’alaikum Wr. Wb.`⏎`Shalom \| Salam Sejahtera`⏎⏎`🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟`⏎⏎`Dengan hormat, kami sampaikan Invoice Pemesanan Tiket Event.` |
| nama_event | `🎪 *{nama_event_besar}*`⏎`📅 {tanggal_event_besar}`⏎`⏰ START NAIK PUKUL 10.00 WIB` |
| pemesan | `👤 Nama     : {nama_pemesan}`⏎`🔢 No. Urut : {nomor_urut}` |
| kelas_pesanan | judul `🎟️ KELAS PESANAN`, format baris `{no}. {nama_sesi} — {harga} = {qty}` |
| total_pembayaran | `💰 TOTAL PEMBAYARAN`⏎`👉 Rp {total_angka}` |
| ketentuan_pembayaran | `⚠️ KETENTUAN PEMBAYARAN`⏎`Pembayaran maksimal 1 × 24 jam setelah invoice diterima.`⏎⏎`💳 TRANSFER PEMBAYARAN`⏎`🏦 BCA — 1234567890`⏎`👤 A/N Nama Pemilik`⏎`📲 Konfirmasi: 0812-0000-0000` |
| penutup | `🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA`⏎⏎`Maturnuwun & Salam Nyawiji. ❤️` |
| iklan | `📍 LOKASI GANTANGAN`⏎`https://contoh.example/lokasi`⏎⏎`👥 GABUNG KOMUNITAS`⏎`https://contoh.example/komunitas` |

**Hasil render yang diharapkan** (`━━━━━━━━━━━━━━━━━━` = pemisah, muncul 7 kali):
```
Assalamu’alaikum Wr. Wb.
Shalom | Salam Sejahtera

🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟

Dengan hormat, kami sampaikan Invoice Pemesanan Tiket Event.
━━━━━━━━━━━━━━━━━━
🎪 *1ST ANNIVERSARY SOLO HIGH CLASS*
📅 MINGGU, 11 OKTOBER 2026
⏰ START NAIK PUKUL 10.00 WIB
━━━━━━━━━━━━━━━━━━
👤 Nama     : Rizal Medan
🔢 No. Urut : 277
━━━━━━━━━━━━━━━━━━
🎟️ KELAS PESANAN
1. MB A PAKET ANNIVERSARY — Rp4.500.000 = 1
━━━━━━━━━━━━━━━━━━
💰 TOTAL PEMBAYARAN
👉 Rp 4.500.000
━━━━━━━━━━━━━━━━━━
⚠️ KETENTUAN PEMBAYARAN
Pembayaran maksimal 1 × 24 jam setelah invoice diterima.

💳 TRANSFER PEMBAYARAN
🏦 BCA — 1234567890
👤 A/N Nama Pemilik
📲 Konfirmasi: 0812-0000-0000
━━━━━━━━━━━━━━━━━━
🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA

Maturnuwun & Salam Nyawiji. ❤️
━━━━━━━━━━━━━━━━━━
📍 LOKASI GANTANGAN
https://contoh.example/lokasi

👥 GABUNG KOMUNITAS
https://contoh.example/komunitas
```
**Variasi uji:** (a) tambahkan sesi kedua ke invoice → baris `2. ...` muncul; (b) matikan bagian `iklan` → bagian terakhir dan satu garis di atasnya hilang; (c) nama pemesan `{total}` → tampil `{total}` apa adanya.
