<?php

return [
    'pemisah' => '━━━━━━━━━━━━━━━━━━',
    'bagian' => [
        'salam_pembuka' => [
            'aktif' => true,
            'teks' => "Assalamu’alaikum Wr. Wb.\nShalom | Salam Sejahtera\n\n🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟\n\nDengan hormat, kami sampaikan Invoice Pemesanan Tiket Event.",
        ],
        'nama_event' => [
            'aktif' => true,
            'teks' => "🎪 *{nama_event_besar}*\n📅 {tanggal_event_besar}\n⏰ START NAIK PUKUL 10.00 WIB",
        ],
        'pemesan' => [
            'aktif' => true,
            'teks' => "👤 Nama     : {nama_pemesan}\n🔢 No. Urut : {nomor_urut}",
        ],
        'kelas_pesanan' => [
            'aktif' => true,
            'teks' => "🎟️ KELAS PESANAN",
            'format_baris' => "{no}. {nama_sesi} — {harga} = {qty}",
        ],
        'total_pembayaran' => [
            'aktif' => true,
            'teks' => "💰 TOTAL PEMBAYARAN\n👉 Rp {total_angka}\n📌 STATUS : {status_ikon} {status}",
        ],
        'ketentuan_pembayaran' => [
            'aktif' => true,
            'teks' => "⚠️ KETENTUAN PEMBAYARAN\nPembayaran maksimal 1 × 24 jam setelah invoice diterima.\n\n💳 TRANSFER PEMBAYARAN\n🏦 BCA — 1234567890\n👤 A/N Nama Pemilik\n📲 Konfirmasi: 0812-0000-0000",
        ],
        'penutup' => [
            'aktif' => true,
            'teks' => "🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA\n\nMaturnuwun & Salam Nyawiji. ❤️",
        ],
        'iklan' => [
            'aktif' => false,
            'teks' => "",
        ],
    ],
];
