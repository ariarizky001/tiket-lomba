# PRD Revisi Tahap 3 — Dropdown Sesi Kosong di Form Tambah Peserta

> Instruksi kerja untuk agent. Ini perbaikan bug (revisi), bukan fitur baru. Kerjakan HANYA yang tertulis di sini dan jangan mengubah backend. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel Mix 6, Babel 7). Lingkungan Windows PowerShell.

## 1. Masalah
Di Ruang Event → tab **Peserta & Invoice** → **Tambah Peserta**, setelah nama peserta diisi, dropdown **Sesi** kosong (tidak ada pilihan), padahal event sudah punya sesi di tab Sesi.

## 2. Fakta yang sudah dipastikan (jangan diubah)
Backend sudah benar. `GET /api/events/{event}/sessions` mengembalikan **array langsung** (bukan dibungkus `data`):
```json
[
  {
    "id": 1,
    "event_id": 1,
    "nama": "MURAI BATU B NYAWIJI",
    "harga": 100000,
    "kuota": 24,
    "created_at": "...",
    "updated_at": "...",
    "invoice_items_sum_qty": null,
    "terjual": 0,
    "sisa": 24
  }
]
```
Field `invoice_items_sum_qty` boleh diabaikan. `terjual` dan `sisa` bisa bertipe angka atau string, jadi selalu dikonversi dengan `Number()`.

Kesimpulan: bug berada di **frontend** (`InvoiceTab.js` / `InvoiceForm.js`). Dugaan penyebab, periksa semuanya:
- `InvoiceTab` tidak memuat sesi sendiri (hanya mengandalkan data dari tab Sesi, atau tidak pernah memanggil API)
- Respons dibaca dari `res.data.data` padahal berupa array di `res.data`
- Opsi Select difilter dengan kondisi yang salah (mis. `sisa > 0` saat `sisa` undefined/string)
- Nama prop tidak cocok (`sessions` vs `eventSessions`) atau default state tidak berupa array
- `value` Select bertipe berbeda dari `event_session_id` (string vs angka)
- Nama field di `Form.List` tidak sesuai dengan yang dibaca saat submit

## 3. Scope Revisi
Hanya file frontend di `resources/js/client/admin/components/invoice/` (dan helper `Utils.js` bila perlu).

## 4. Kebutuhan Perbaikan
1. **Muat sesi di `InvoiceTab`**: panggil `GET /events/{eventId}/sessions` saat komponen mount **dan** setiap kali drawer Tambah Peserta atau Edit dibuka. Simpan di state lokal (default `[]`) dan teruskan ke `InvoiceForm` sebagai prop `sessions`. Tidak boleh bergantung pada `SessionTab` atau tab lain.
2. **Baca respons** sebagai array langsung di `res.data`.
3. **Bangun opsi Select** dari array itu:
   ```js
   options = sessions.map(s => ({
     value: s.id,
     label: `${s.nama} — ${formatRupiah(s.harga)} — sisa ${s.sisa}`,
     disabled: Number(s.sisa) <= 0,
   }))
   ```
   Jangan menyembunyikan sesi dengan filter apa pun. Sesi yang habis cukup `disabled`.
4. **Sesi yang sudah dipilih di baris lain** tidak muncul lagi di baris berikutnya (disaring hanya untuk alasan ini).
5. **Saat edit invoice**, sesi yang sudah ada di invoice itu tidak boleh `disabled` walau `sisa = 0`.
6. **Tipe data**: `value` Select bertipe angka dan sama dengan `event_session_id`. Nama field `Form.List` konsisten, misalnya `name={[field.name, 'event_session_id']}` dan `name={[field.name, 'qty']}`.
7. **Kondisi kosong dan error**: `sessions` kosong → tampilkan pesan "Belum ada sesi di event ini" pada form. Request gagal → `message.error`.
8. **Subtotal dan total** di form tetap terhitung otomatis dari `harga × qty` sesi yang dipilih (sesuai PRD Tahap 3).
9. **Debug sementara**: tambahkan `console.log` jumlah sesi yang diterima dan opsi yang dibangun. **Hapus semua log itu sebelum selesai.**

## 5. Di Luar Scope
Perubahan backend, API, database, tab/halaman lain, library baru, dan fitur baru.

## 6. Acceptance Criteria (semua harus lolos)
- [ ] Buka Tambah Peserta pada event yang punya sesi: dropdown menampilkan `MURAI BATU B NYAWIJI — Rp 100.000 — sisa 24`
- [ ] Memilih sesi mengisi harga, subtotal, dan total otomatis; qty mengubah subtotal dan total
- [ ] Menambah baris kedua: sesi yang sudah dipilih tidak muncul lagi
- [ ] Simpan berhasil, invoice muncul di tabel, tab Sesi menampilkan `Terjual` dan `Sisa` yang ikut berubah
- [ ] Sesi dengan `sisa = 0` tampil tetapi tidak bisa dipilih
- [ ] Edit invoice: sesi yang sudah dibeli tetap bisa dipilih
- [ ] Event tanpa sesi: form menampilkan "Belum ada sesi di event ini", tidak error
- [ ] Menutup lalu membuka drawer lagi tetap menampilkan sesi (tidak bergantung pada tab Sesi yang pernah dibuka)
- [ ] Tidak ada error di Console browser dan tidak ada `console.log` debug tersisa
- [ ] Fitur Tahap 1 sampai 3 lainnya tetap berjalan; tidak ada library baru

## 7. Output yang Diharapkan dari Agent
1. Kode perbaikan pada file yang disentuh
2. **Penyebab bug yang ditemukan** (satu sampai dua kalimat)
3. Perintah yang perlu dijalankan (`npm run dev`)
4. Hasil centang Acceptance Criteria
