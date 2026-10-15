import React, { useMemo } from 'react';

// --- Helper untuk membuat mock data ---
const generateMockData = () => {
  const sessionTitles = [
    { title: 'MB A SAZIME', price: '550.000' },
    { title: 'MB A PAKET NYAWIJI', price: '1.100.000' },
    { title: 'MB A PAKET SEDULURAN', price: '2.200.000' },
    { title: 'MB PRINSIP', price: '2.200.000' },
    { title: 'MB A PAKET INTEGRITAS', price: '4.500.000' }
  ];

  // Daftar nama acak untuk simulasi data
  const sampleNames = [
    "RENDI SF", "DT KUDA TERBANG", "PUTRA KATONG", "ADAM FIKRI", "FENDI SALATIGA",
    "KJ PRIO MJS", "PAMAN DIKO", "GUS SHOLAH", "DR RIZAL", "ABAH HUDAN",
    "DEWANGGA", "H BETHET", "BLACK JAZZ", "ABAH KODI", "SANTOS BF",
    "DAWAM", "BINTANG PRADANA", "BIMA SBY", "RUDY NRS"
  ];

  return sessionTitles.map((session, index) => {
    // Generate 24 peserta untuk setiap sesi
    const participants = Array.from({ length: 24 }).map((_, pIndex) => {
      // Mock logic: Sesekali ada yang kosong, sesekali ada warna khusus
      const hasParticipant = Math.random() > 0.15; // 85% terisi
      const name = hasParticipant ? sampleNames[Math.floor(Math.random() * sampleNames.length)] : "";
      
      let status = 'normal';
      if (hasParticipant) {
        const rand = Math.random();
        if (rand > 0.85) status = 'merah';
        else if (rand > 0.70) status = 'kuning';
      }

      return {
        id: pIndex + 1,
        name: name,
        status: status
      };
    });

    return {
      id: index + 1,
      title: session.title,
      price: session.price,
      participants: participants
    };
  });
};

export default function App() {
  // Hanya di-generate sekali saat komponen di-mount
  const sessions = useMemo(() => generateMockData(), []);

  return (
    // Wrapper utama: Full screen, background gelap, mencegah scroll
    <div className="h-screen w-screen bg-[#2d2d2d] flex flex-col p-2 overflow-hidden font-sans select-none">
      
      {/* Header Aplikasi (Otomatis mengambil ruang seperlunya) */}
      <header className="flex-shrink-0 text-center mb-2">
        <h1 className="text-yellow-500 font-black text-lg md:text-xl tracking-wide uppercase drop-shadow-md leading-none mb-1">
          1st Anniversary Solo High Class
        </h1>
        <p className="text-gray-200 text-[10px] font-bold tracking-wider leading-none">
          MINGGU, 11 OKTOBER 2026 - SOLO HIGH CLASS STADIUM
        </p>
      </header>

      {/* Grid 5 Kolom (Mengambil sisa ruang layar sepenuhnya) */}
      <main className="flex-1 min-h-0 grid grid-cols-5 gap-1.5 w-full">
        {sessions.map((session) => (
          
          // Card per Sesi
          <div 
            key={session.id} 
            className="flex flex-col h-full bg-gray-300 border border-gray-600 rounded-sm overflow-hidden shadow-lg"
          >
            {/* Header Sesi */}
            <div className="flex-shrink-0 flex items-center bg-[#94a3b8] p-1 border-b border-gray-600 shadow-sm">
              <span className="bg-gray-800 text-white font-bold px-1.5 py-0.5 rounded-sm text-[9px] leading-none">
                {session.id}
              </span>
              <span className="flex-1 text-center font-black text-gray-900 text-[9px] sm:text-[10px] uppercase truncate px-1">
                {session.title}
              </span>
            </div>

            {/* Sub-header Harga (Kuning) */}
            <div className="flex-shrink-0 bg-[#facc15] text-gray-900 text-center font-black text-[10px] py-[2px] border-b border-gray-600">
              {session.price}
            </div>

            {/* Daftar 24 Peserta (Membagi rata sisa ruang vertikal card) */}
            <div className="flex flex-col flex-1 min-h-0">
              {session.participants.map((participant, index) => {
                
                // Zebra Striping: index genap bg-gray-200, ganjil bg-gray-300
                const isEven = index % 2 === 0;
                const bgClass = isEven ? 'bg-[#e5e5e5]' : 'bg-[#d4d4d4]';
                
                // Logika Warna berdasarkan status
                let colorClass = 'text-gray-900';
                if (participant.status === 'merah') {
                  colorClass = 'text-red-700 font-black';
                } else if (participant.status === 'kuning') {
                  colorClass = 'text-yellow-700 font-black'; // Gunakan yellow-700 agar terbaca di bg terang
                } else {
                  colorClass = 'text-gray-800 font-bold';
                }

                return (
                  // Baris Peserta (flex-1 memastikan membagi tinggi rata tanpa scroll)
                  <div 
                    key={participant.id} 
                    className={`flex-1 flex items-center px-1.5 ${bgClass} border-b border-gray-400/40`}
                  >
                    {/* Nomer Gantangan (Kiri) */}
                    <span className="w-4 flex-shrink-0 text-left font-bold text-gray-600 text-[9px] leading-none">
                      {participant.id}
                    </span>
                    
                    {/* Nama Peserta */}
                    <span className={`flex-1 truncate text-[9px] uppercase leading-none ${colorClass}`}>
                      {participant.name}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </main>

    </div>
  );
}