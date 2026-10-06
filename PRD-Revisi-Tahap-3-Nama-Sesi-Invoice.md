# PRD Revisi Tahap 3 — Nama Sesi `undefined` di Invoice

> Instruksi kerja untuk agent. Ini perbaikan bug (revisi), bukan fitur baru. Kerjakan HANYA yang tertulis di sini. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Laravel Mix 6). Lingkungan Windows PowerShell.

## 1. Masalah
Setelah peserta dibuat dan tersimpan, tampilan invoice (dan teks WhatsApp) menampilkan nama sesi `undefined`, sementara qty dan harga benar:
```
*INVOICE LATP-0001*
Event: LATPRES SHC
Nama: aria

undefined x1 = Rp 100.000
*Total: Rp 100.000*
Status: BELUM LUNAS
```
Sumber data yang sama kemungkinan juga membuat kolom **Sesi Dibeli** di tabel invoice (tag `Nama Sesi ×qty`) dan tampilan cetak ikut kosong atau `undefined`.

## 2. Analisis
Karena qty dan subtotal tampil benar, data item sampai ke frontend. Yang hilang hanya **nama sesi**. Penyebab yang perlu diperiksa:
- Backend tidak meng-*eager load* relasi `items.eventSession` (atau tidak mengirim `session_nama`) pada salah satu respons: `index`, `store`, `update`, `show`, `PATCH status`. Respons `store` yang dipakai untuk langsung membuka InvoiceView termasuk yang paling sering lupa memuat relasi.
- Backend mengirim nama lewat relasi (`items[].event_session.nama`), tetapi frontend membaca `item.session_nama` (atau sebaliknya).
- Relasi dikirim dengan nama berbeda (`eventSession` vs `event_session`) karena serialisasi snake_case.

Kontrak yang disepakati di PRD Tahap 3: setiap item invoice membawa **`session_nama`**.

## 3. Scope Revisi
Backend: `InvoiceController`, model `InvoiceItem`, serta Resource/serialisasi invoice bila ada. Frontend: komponen di `resources/js/client/admin/components/invoice/` dan `Utils.js` bila perlu. **Tanpa mengubah skema database dan tanpa library baru.**

## 4. Kebutuhan Perbaikan
**Backend**
1. Semua respons yang mengembalikan invoice (`index`, `show`, `store`, `update`, `PATCH status`) harus memuat item beserta nama sesinya: `->load('items.eventSession:id,nama')` (atau `with(...)` pada query). Pada `store` dan `update`, lakukan `load` **setelah** item tersimpan sebelum mengembalikan respons.
2. Pada model `InvoiceItem`, tambahkan accessor dan `$appends`:
   ```php
   protected $appends = ['session_nama'];
   public function getSessionNamaAttribute() { return $this->eventSession?->nama; }
   ```
   Pakai `$hidden = ['eventSession']` bila tidak ingin relasi mentahnya ikut terkirim.
3. Bentuk item dalam respons konsisten di semua endpoint:
   ```json
   { "id": 1, "event_session_id": 1, "session_nama": "MURAI BATU B NYAWIJI", "qty": 1, "harga": 100000, "subtotal": 100000 }
   ```
4. Hindari N+1 pada `index` (eager load, bukan lazy load di dalam loop).

**Frontend**
5. Seluruh pemakaian nama sesi memakai satu helper di `Utils.js`:
   ```js
   export const sessionName = (item) => item.session_nama || item.event_session?.nama || item.eventSession?.nama || `Sesi #${item.event_session_id}`;
   ```
   Gunakan helper ini di: tag **Sesi Dibeli** pada tabel, **InvoiceView** (tabel item), **teks WhatsApp**, **tampilan cetak**, dan **form Edit** (bila menampilkan nama sesi).
6. Format teks WhatsApp tetap sama, hanya nama sesi yang diperbaiki:
   ```
   {Nama Sesi} x{qty} = Rp {subtotal}
   ```
7. Setelah **Buat Peserta**, InvoiceView yang langsung terbuka harus memakai data dari respons `store` (yang kini sudah memuat `session_nama`), tanpa perlu refresh halaman.

## 5. Di Luar Scope
Perubahan skema database, fitur baru, tampilan di luar invoice, library baru.

## 6. Acceptance Criteria (semua harus lolos)
- [ ] Buat peserta baru: InvoiceView yang langsung terbuka menampilkan nama sesi yang benar (bukan `undefined`)
- [ ] Teks **Salin untuk WhatsApp** menampilkan `NAMA SESI x1 = Rp ...`
- [ ] Kolom **Sesi Dibeli** di tabel menampilkan `Nama Sesi ×qty` untuk semua baris, termasuk setelah refresh halaman
- [ ] Tampilan **Cetak** menampilkan nama sesi
- [ ] Invoice dengan lebih dari satu sesi menampilkan semua nama sesi dengan benar
- [ ] Setelah Edit invoice dan setelah **Tandai Lunas/Belum Lunas**, nama sesi tetap tampil
- [ ] Respons `GET /api/events/{event}/invoices` dan `GET /api/invoices/{id}` memuat `session_nama` di setiap item (cek lewat Postman)
- [ ] Tidak ada query N+1 pada daftar invoice
- [ ] Tidak ada error di Console browser; fitur Tahap 1 sampai 3 lainnya tetap berjalan; tidak ada library baru

## 7. Output yang Diharapkan dari Agent
1. Kode perbaikan pada file yang disentuh
2. **Penyebab bug yang ditemukan** (satu sampai dua kalimat)
3. Perintah yang perlu dijalankan (`php artisan route:clear` bila perlu, `npm run dev`)
4. Hasil centang Acceptance Criteria
