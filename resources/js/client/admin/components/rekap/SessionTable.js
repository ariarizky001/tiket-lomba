import React, { useMemo } from 'react';
import { formatAngka } from '../../../common/helpers/Utils';
import SendToSheetButton from '../spreadsheet/SendToSheetButton';

const SessionTable = ({ 
  sesi, 
  eventId, 
  isDashboard = false,
  rowHeight = 22,
  fontSize = 12,
  headerFontSize = 12,
  cellPaddingV = 2,
  cellPaddingH = 6,
}) => {
  const { nama, harga, kuota, peserta, id: sessionId, urutan } = sesi;

  // Build rows from peserta data
  const rows = useMemo(() => {
    const rows = [];
    const filled = peserta || [];
    let no = 1;

    // Filled rows
    filled.forEach((p) => {
      rows.push({
        no: no++,
        nama: p.nama,
        status: p.status,
        invoice_id: p.invoice_id,
      });
    });

    // Empty rows
    const emptyCount = Math.max(0, kuota - filled.length);
    for (let i = 0; i < emptyCount; i++) {
      rows.push({
        no: filled.length + i + 1,
        nama: '',
        status: 'kosong',
        invoice_id: null,
      });
    }

    return rows;
  }, [peserta, kuota]);

  const tableStyle = {
    fontSize: `${fontSize}px`,
    lineHeight: 1.15,
  };

  const headerRowStyle = {
    height: `${rowHeight}px`,
  };

  const cellStyle = {
    padding: `${cellPaddingV}px ${cellPaddingH}px`,
    fontSize: `${fontSize}px`,
    height: `${rowHeight}px`,
  };

  const headerCellStyle = {
    padding: `${cellPaddingV}px ${cellPaddingH}px`,
    fontSize: `${headerFontSize}px`,
    height: `${rowHeight}px`,
  };

  return (
    <div className="rekap-table-wrapper rekap-dashboard-table">
      <table className="rekap-table" style={tableStyle}>
        <colgroup>
          <col style={{ width: '30px', minWidth: '30px' }} />
          <col style={{ width: 'auto' }} />
        </colgroup>
{/* Header row 1: Sesi {urutan} {nama} */}
          <thead>
            <tr className="rekap-header-row-1" style={headerRowStyle}>
              <th colSpan="2" className="rekap-session-name" title={nama} style={headerCellStyle}>
                <div className="flex items-center justify-between" style={{ padding: '0 4px', height: '100%' }}>
                  <span style={{ fontSize: `${headerFontSize}px`, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    Sesi {urutan} {nama}
                  </span>
                  {!isDashboard && eventId && (
                    <SendToSheetButton
                      eventId={eventId}
                      sessionId={sessionId}
                      sessionName={nama}
                    />
                  )}
                </div>
              </th>
            </tr>
           {/* Header row 2: Harga */}
           <tr className="rekap-header-row-2" style={headerRowStyle}>
             <th colSpan="2" className="rekap-session-price" style={headerCellStyle}>
               {formatAngka(harga)}
             </th>
           </tr>
           {/* Header row 3: Column headers */}
           <tr className="rekap-header-row-3" style={headerRowStyle}>
             <th className="rekap-col-no" style={headerCellStyle}>No</th>
             <th className="rekap-col-nama" style={headerCellStyle}>Nama</th>
           </tr>
         </thead>
        <tbody>
          {rows.map((row, idx) => (
            <tr key={idx} className={idx % 2 === 0 ? 'rekap-row-even' : 'rekap-row-odd'} style={headerRowStyle}>
              <td className="rekap-cell-no" style={cellStyle}>{row.no}</td>
              <td className="rekap-cell-nama" style={cellStyle}>
                {row.nama ? (
                  <span
                    className={`rekap-nama ${row.status === 'lunas' ? 'rekap-lunas' : row.status === 'belum_lunas' ? 'rekap-belum-lunas' : ''}`}
                    title={row.nama}
                  >
                    {row.nama}
                  </span>
                ) : (
                  <span className="rekap-empty">—</span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default SessionTable;