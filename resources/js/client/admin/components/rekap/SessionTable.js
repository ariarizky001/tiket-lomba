import React, { useMemo } from 'react';
import { formatAngka } from '../../../common/helpers/Utils';

const SessionTable = ({ sesi }) => {
  const { nama, harga, kuota, peserta } = sesi;

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

  return (
    <div className="rekap-table-wrapper">
      <table className="rekap-table">
        {/* Header row 1: Sesi {urutan} {nama} */}
        <thead>
          <tr className="rekap-header-row-1">
            <th colSpan="2" className="rekap-session-name" title={nama}>
              Sesi {sesi.urutan} {nama}
            </th>
          </tr>
          {/* Header row 2: Harga */}
          <tr className="rekap-header-row-2">
            <th colSpan="2" className="rekap-session-price">
              {formatAngka(harga)}
            </th>
          </tr>
          {/* Header row 3: Column headers */}
          <tr className="rekap-header-row-3">
            <th className="rekap-col-no">No</th>
            <th className="rekap-col-nama">Nama</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row, idx) => (
            <tr key={idx} className={idx % 2 === 0 ? 'rekap-row-even' : 'rekap-row-odd'}>
              <td className="rekap-cell-no">{row.no}</td>
              <td className="rekap-cell-nama">
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