# PRD Perbaikan — Layar Blank Saat Membuka Kolom Tanggal (Menu Latber)

> Instruksi kerja untuk agent. Ini perbaikan bug, **bukan fitur baru**. Kerjakan HANYA yang tertulis di sini. **Jangan menebak penyebab: buktikan dulu lewat diagnosis (bagian 3).** Tech stack tidak berubah (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru** (tanggal memakai `moment` yang sudah menjadi dependensi antd 4; jangan memasang dayjs atau library tanggal lain). Lingkungan Windows PowerShell.

## 1. Gejala
Pada halaman menu **Latber** (`/latbers`), drawer **Tambah Latber**: kolom **Tanggal** (antd `DatePicker`, terisi otomatis `16/11/2026`) menampilkan kursor teks saat disorot. Ketika **diklik**, seluruh halaman berubah **kosong/putih** (aplikasi berhenti merender). Dugaan: terjadi error JavaScript saat kalender dibuka. Kolom filter rentang tanggal di toolbar daftar (`Start date` / `End date`) juga berbahasa Inggris, menandakan lokalisasi tanggal belum diatur.

## 2. Penjelasan (untuk pemilik proyek)
- **Kursor berbentuk "ketik" adalah perilaku normal** antd `DatePicker`: kolomnya bisa diketik manual. Ini bukan bug, tetapi kurang nyaman (di HP keyboard ikut muncul). Akan diubah agar klik langsung membuka kalender.
- **Layar kosong sesudah klik adalah bug sungguhan.** Di React, error yang terjadi saat render dan tidak ditangkap membuat **seluruh aplikasi hilang**. Penyebab yang umum pada antd 4 + moment, dan yang harus dibuktikan atau disingkirkan:
  1. nilai tanggal yang masuk ke `DatePicker`/`RangePicker` bukan objek `moment` (mis. string `"2026-11-16"`, objek `Date`, atau `null` yang tidak ditangani)
  2. objek `locale` buatan sendiri yang tidak lengkap (mis. nama hari/bulan hilang) atau jalur impor locale yang salah
  3. lebih dari satu salinan `moment` terpasang sehingga pemeriksaan `isMoment` gagal
  4. `getPopupContainer` yang mengambil elemen `null` (mis. `trigger.parentNode`) di dalam `Drawer`
  5. `onChange` memanggil `.format()` pada nilai `null` (saat dihapus/dikosongkan)
  6. `disabledDate` atau prop kustom lain yang melempar error
- Kotak kecil bertulis `16/11/2026` di bawah kolom pada tangkapan layar adalah **tooltip bawaan browser**, bukan bagian bug.
- Tidak ada pelindung error (Error Boundary), sehingga satu error kecil menghapus seluruh tampilan.

## 3. Diagnosis (WAJIB dikerjakan lebih dulu)
Catat hasil di `docs/DIAGNOSIS-DATEPICKER.md`:
| # | Langkah | Yang dicatat |
|---|---|---|
| D1 | Reproduksi: buka `/latbers` → Tambah Latber → klik kolom Tanggal, dengan DevTools Console terbuka (**Preserve log**) | Pesan error lengkap, nama file/baris, dan *component stack* |
| D2 | Ulangi pada: filter rentang tanggal di toolbar `/latbers`, mode **Edit** Latber, form Event (jika memakai tanggal), drawer dan filter tanggal Pengeluaran | Tabel: lokasi, apakah crash, pesan error |
| D3 | `grep` semua `DatePicker`, `RangePicker`, `moment`, `dayjs`, `locale`, `getPopupContainer`, `disabledDate`, `initialValue`, `setFieldsValue`, `toISOString` di `resources/js` | Daftar semua pemakaian dan cara nilai dikonversi |
| D4 | `npm ls moment` dan `npm ls dayjs` | Jumlah salinan dan versi `moment`; ada `dayjs` atau tidak |
| D5 | Periksa apakah ada `ConfigProvider` dan `locale` (antd, moment) di entry `app.js`; periksa jalur impor locale | Hasil |
| D6 | Periksa penanganan nilai `null` pada setiap `onChange` tanggal | Daftar lokasi bermasalah |

**Kesimpulan diagnosis** ditulis eksplisit: penyebab terbukti, yang disingkirkan, dan yang belum pasti.

## 4. Scope Perbaikan
1. Memperbaiki penyebab crash yang terbukti
2. Komponen tanggal **seragam** untuk seluruh aplikasi (satu cara pakai, satu cara konversi)
3. Lokalisasi tanggal berbahasa Indonesia
4. **Error Boundary** agar error tidak menghapus seluruh halaman
5. Kolom tanggal membuka kalender saat diklik (tanpa mengetik, tanpa keyboard HP)

## 5. Di Luar Scope
Fitur baru, perubahan tampilan di luar komponen tanggal, perubahan backend atau skema, library baru.

## 6. Kebutuhan Perbaikan
**6.1 Komponen dan helper seragam**
- Buat `resources/js/client/common/components/AppDatePicker.js` dan `AppRangePicker.js`, pembungkus antd dengan bawaan: `format="DD/MM/YYYY"`, `inputReadOnly` (klik langsung membuka kalender), `allowClear`, lebar penuh, placeholder Indonesia (`Pilih tanggal`; `Mulai` dan `Sampai` untuk rentang). **Jangan** memakai `getPopupContainer` kustom kecuali terbukti perlu; bila perlu, pakai fungsi aman yang tidak pernah memakai elemen `null`
- Tambahkan di `Utils.js`:
  - `toMoment(value)`: terima string `YYYY-MM-DD`, `null`, atau `undefined`; kembalikan objek `moment` yang valid atau `null` (tidak pernah melempar error, tidak pernah mengembalikan moment tidak valid)
  - `toApiDate(m)`: terima `moment` atau `null`; kembalikan string `YYYY-MM-DD` (memakai `.format('YYYY-MM-DD')`, **jangan** `toISOString()` karena menggeser tanggal akibat zona waktu) atau `null`
  - `todayMoment()`: tanggal hari ini sebagai `moment`
- **Semua** form dan filter tanggal di aplikasi (Latber, Event, Pengeluaran, dan lainnya hasil temuan D3) memakai komponen dan helper ini. Form hanya menyimpan nilai `moment`/`null`, dan konversi ke string terjadi **hanya** saat kirim ke API dan saat mengisi form dari data API
- Semua `onChange` menangani `null` (tanggal dikosongkan) tanpa error

**6.2 Lokalisasi**
- Di entry `app.js`, bungkus aplikasi dengan antd `ConfigProvider` memakai locale Indonesia bawaan antd (`id_ID`; pilih jalur impor yang benar-benar ada pada versi antd terpasang) dan panggil `moment.locale('id')` dengan mengimpor `moment/locale/id`. **Jangan** membuat objek locale buatan sendiri
- Hasil: nama bulan dan hari berbahasa Indonesia, placeholder Indonesia

**6.3 Satu salinan moment**
- Pastikan `npm ls moment` hanya menampilkan satu versi. Bila ganda, selesaikan tanpa menambah library (mis. `npm dedupe`, atau alias `moment` di `webpack.mix.js` melalui `mix.webpackConfig({ resolve: { alias: { moment: path.resolve('node_modules/moment') } } })`)

**6.4 Error Boundary**
- Buat `common/components/ErrorBoundary.js` (komponen kelas React 17 dengan `componentDidCatch`). Saat error render: tampilkan antd `Result` status `error` dengan judul "Terjadi kesalahan pada halaman ini", keterangan singkat, tombol **Muat Ulang Halaman**, dan tombol **Kembali ke Beranda**; cetak error ke `console.error`
- Pasang **dua lapis**: satu membungkus seluruh isi `AppLayout` (header dan menu tetap tampil), satu membungkus tiap tab di Ruang Event/Ruang Latber. Error di satu tab tidak boleh menghapus tab lain atau header
- Pesan error teknis tidak ditampilkan ke pengguna pada production (hanya di console)

**6.5 Perbaikan penyebab spesifik**
- Perbaiki **apa pun** penyebab terbukti dari bagian 3 (nilai non-moment, locale buatan, popup container, `null` pada `onChange`, dll.) dan catat di laporan

## 7. Uji Manual (tanpa library tes frontend)
| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| T1 | `/latbers` → Tambah Latber → klik kolom Tanggal | Kalender terbuka, tidak ada layar kosong, kursor pointer, keyboard HP tidak muncul |
| T2 | Pilih `1 Desember 2026` → Simpan → cek tabel dan database (phpMyAdmin) | Tanggal tersimpan `2026-12-01`, tampil `01/12/2026` (tidak mundur sehari) |
| T3 | Ulangi dengan `31 Desember` dan `1 Januari` | Sama, tanpa pergeseran |
| T4 | Edit Latber | Kolom terisi tanggal tersimpan; kalender terbuka tanpa error |
| T5 | Kolom tanggal dikosongkan (ikon hapus) lalu Simpan | Validasi "wajib" tampil, tidak crash |
| T6 | Filter rentang tanggal di `/latbers`: buka, pilih rentang, hapus rentang | Tabel terfilter dan pulih; placeholder `Mulai` dan `Sampai`; kalender berbahasa Indonesia |
| T7 | Form Event dan Pengeluaran (drawer dan filter) bila memakai tanggal | Sama seperti T1 sampai T6 |
| T8 | **Uji Error Boundary:** untuk sementara lempar error pada salah satu tab (mis. `throw new Error('uji')` di komponen tab), buka tab itu | Hanya tab itu yang menampilkan pesan error; header dan menu tetap; tab lain normal. **Hapus baris uji sebelum selesai** |
| T9 | Lebar layar 375px | Kalender tampil utuh dan bisa dipakai |

## 8. Acceptance Criteria (semua harus lolos)
- [ ] `docs/DIAGNOSIS-DATEPICKER.md` berisi hasil D1 sampai D6 dan kesimpulan eksplisit (penyebab terbukti)
- [ ] T1 sampai T9 lolos; tidak ada layar kosong pada skenario mana pun
- [ ] Semua pemakaian `DatePicker`/`RangePicker` di `resources/js` memakai `AppDatePicker`/`AppRangePicker` dan helper `toMoment`/`toApiDate` (bukti `grep` di laporan; tidak ada pemakaian `toISOString` untuk tanggal)
- [ ] `npm ls moment` menampilkan satu versi; tidak ada `dayjs` ataupun library baru
- [ ] Tidak ada error atau peringatan terkait moment/antd di Console saat membuka kalender
- [ ] `npm run prod` sukses; fitur Latber, Event, Pengeluaran, dan modul lain tetap berfungsi

## 9. Output yang Diharapkan dari Agent
1. `docs/DIAGNOSIS-DATEPICKER.md` (hasil dan kesimpulan)
2. Kode perbaikan (komponen tanggal, helper, locale, Error Boundary, penyebab spesifik)
3. Daftar file yang diubah dan alasan singkat tiap perubahan
4. Daftar asumsi dan hasil centang Acceptance Criteria
