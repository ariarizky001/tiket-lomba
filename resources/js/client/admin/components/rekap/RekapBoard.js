import React, { useMemo } from 'react';
import SessionTable from './SessionTable';

const RekapBoard = ({ sesi }) => {
  return (
    <div className="rekap-grid">
      {sesi.map((s) => (
        <SessionTable key={s.id} sesi={s} />
      ))}
    </div>
  );
};

export default RekapBoard;