import React, { useMemo } from 'react';

const GantanganGrid = ({ sesi }) => {
  // 1. SAFETY CHECK: Jika data sesi belum ada/loading, jangan render apa-apa
  if (!sesi) return null;

  // 2. DEFAULT VALUES: Berikan nilai default kosong (misal peserta = []) agar map/forEach tidak crash
  const { id = '-', nama = 'Sesi Kosong', harga = 0, kuota = 24, peserta = [] } = sesi;

  // Build participant map for quick lookup by slot number
  const participantMap = useMemo(() => {
    const map = {};
    // Karena sudah di-default ke [], forEach akan aman meskipun dari API peserta tidak ada
    peserta.forEach((p, idx) => {
      map[idx + 1] = p; // Menggunakan urutan index sebagai default, atau p.nomor_gantangan jika ada
    });
    return map;
  }, [peserta]);

  // Count occurrences of each peserta in original data to determine if prefix needed
  const pesertaCount = useMemo(() => {
    const counts = {};
    peserta.forEach(p => {
      // Pastikan p.nama_peserta ada nilainya
      if (p.nama_peserta) {
        counts[p.nama_peserta] = (counts[p.nama_peserta] || 0) + 1;
      }
    });
    return counts;
  }, [peserta]);

  // Generate all slots from 1 to kuota
  const totalSlots = Math.max(kuota, peserta.length);
  const slots = Array.from({ length: totalSlots }, (_, i) => i + 1);

  return (
    <div 
      key={id}
      className="flex flex-col h-full bg-gray-300 border border-gray-600 rounded-sm overflow-hidden shadow-lg w-[420px] min-w-[420px]"
    >
      {/* Header Sesi */}
      <div className="flex-shrink-0 flex items-center bg-[#94a3b8] p-1 border-b border-gray-600 shadow-sm">
        <span className="bg-gray-800 text-white font-bold px-1.5 py-0.5 rounded-sm text-[9px] leading-none">
          {id}
        </span>
        <span className="flex-1 text-center font-black text-gray-900 text-[9px] sm:text-[10px] uppercase truncate px-1">
          {nama}
        </span>
      </div>

      {/* Sub-header Harga (Aman karena sudah di-set nilai default harga = 0) */}
      <div className="flex-shrink-0 bg-[#facc15] text-gray-900 text-center font-black text-[10px] py-[2px] border-b border-gray-600">
        Rp {Number(harga).toLocaleString('id-ID')}
      </div>

      {/* Daftar 24 Peserta */}
      <div className="flex flex-col flex-1 min-h-0">
        {slots.map((slotNum, index) => {
          const participant = participantMap[slotNum];
          const isOccupied = !!participant;
          
          let participantName = '';
          if (isOccupied && participant.nama_peserta) {
            const hasMultiple = pesertaCount[participant.nama_peserta] > 1;
            // Gunakan optional participant.urutan jika ada, atau fallback ke indeks
            const suffix = participant.urutan ? participant.urutan : (index + 1);
            participantName = hasMultiple ? `${participant.nama_peserta} #${suffix}` : participant.nama_peserta;
          }

          // Zebra Striping
          const isEven = index % 2 === 0;
          const bgClass = isEven ? 'bg-[#e5e5e5]' : 'bg-[#d4d4d4]';
          
          let colorClass = 'text-gray-900';
          let fontWeight = 'font-bold';
          let displayText = 'NP'; // Teks default jika slot kosong
          
          if (isOccupied) {
            // Asumsi status pelunasan
            if (participant.status === 'lunas' || participant.status_warna === 'normal') {
              colorClass = 'text-gray-900';
              fontWeight = 'font-bold';
            } else {
              colorClass = 'text-red-700'; // Belum lunas / status khusus
              fontWeight = 'font-black';
            }
            displayText = participantName;
          }

          return (
            <div 
              key={slotNum}
              className={`flex-1 flex items-center px-1.5 ${bgClass} border-b border-gray-400/40`}
            >
              <span className="w-4 flex-shrink-0 text-left font-bold text-gray-600 text-[9px] leading-none">
                {slotNum}
              </span>
              <span className={`flex-1 truncate text-[9px] uppercase leading-none ${colorClass} ${fontWeight}`}>
                {displayText}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default GantanganGrid;