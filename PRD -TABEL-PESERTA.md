# Product Requirements Document (PRD)
**Project:** Dashboard Live View Gantangan  
**Platform:** Web (Backend: Laravel, Frontend: React)  
**Document Version:** 1.0  

---

## 1. Ringkasan Fitur (Overview)
Pembuatan halaman *dashboard* khusus yang bersifat *clean* dan *read-only* untuk menampilkan jadwal, daftar sesi, dan peserta pada suatu *Event* perlombaan burung berkicau. Halaman ini dirancang khusus untuk dirender pada layar besar (TV/Monitor) di area perlombaan sehingga penonton dan peserta dapat melihat jadwal secara *real-time*.

## 2. Prasyarat Sistem (Pre-requisites)
Aplikasi utama telah memiliki fungsionalitas berikut:
*   **Modul Event:** CRUD data event (Nama Event, Tanggal, Lokasi).
*   **Modul Sesi/Kelas:** CRUD data sesi yang berelasi dengan Event (Nomor Sesi, Nama Kelas, Harga Tiket).
*   **Modul Peserta:** CRUD data pendaftaran peserta yang berelasi dengan Sesi (Nama Peserta, Nomor Gantangan, Status Pembayaran/Warna).

## 3. Kebutuhan Antarmuka (UI/UX & Layout)
*   **Clean Layout:** Halaman ini membuang semua elemen navigasi admin (*sidebar*, *navbar*, *footer*). Layar didedikasikan 100% untuk konten data.
*   **Header Judul:** Menampilkan "Nama Event" dan "Tanggal Pelaksanaan" di posisi atas tengah (diambil secara dinamis dari database).
*   **Grid Layout Fleksibel (Max 5 Kolom):** 
    *   Kartu (Card) Sesi ditampilkan menyamping dari kiri ke kanan.
    *   Satu baris memuat **maksimal 5 kartu** (menggunakan `grid-cols-5`).
    *   Jika jumlah sesi lebih dari 5, maka sesi ke-6 akan otomatis turun ke baris bawahnya, begitu seterusnya (menggunakan sistem *wrap* atau *grid* dinamis).
*   **Kerapatan Baris (Dense 24 Row):** 
    *   Setiap kartu sesi **mutlak** memiliki 24 baris nomor gantangan (1-24). 
    *   Tinggi tiap baris harus menyesuaikan sisa tinggi layar secara otomatis (menggunakan *Flexbox*) agar ke-24 baris muat dalam satu pandangan tanpa perlu memunculkan *scroll-bar*.

## 4. Kebutuhan Fungsional & Logika Bisnis (Business Logic)
*   **Pengambilan Data Terpusat:** Halaman menerima parameter `event_id` (via URL parametrik, contoh: `/live-dashboard/{event_id}`) untuk menarik seluruh data Sesi dan Peserta secara spesifik.
*   **Penanganan Duplikasi Nama (Duplicate Handling):**
    *   Sistem harus mendeteksi jika ada peserta dengan nama yang **sama persis** di dalam **1 Sesi yang sama**.
    *   Jika terdeteksi duplikasi, sistem otomatis menambahkan *prefix/suffix* penomoran (contoh: `#1`, `#2`) di belakang nama.
    *   *Skenario:* Jika "RANDY SOLO" mendaftar dua kali di Kelas Sazime (Gantangan 2 dan 7), maka di layar akan tampil "RANDY SOLO #1" dan "RANDY SOLO #