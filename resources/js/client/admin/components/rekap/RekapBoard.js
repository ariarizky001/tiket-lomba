import React, { useMemo } from 'react';
import SessionTable from './SessionTable';

const RekapBoard = ({ sesi, eventId }) => {
  return (
    <div className="rekap-grid">
      {sesi.map((s) => (
        <SessionTable key={s.id} sesi={s} eventId={eventId} />
      ))}
    </div>
  );
};

export default RekapBoard;