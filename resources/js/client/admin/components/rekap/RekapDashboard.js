import React, { useState, useEffect, useMemo } from 'react';
import { Spin, Empty } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import SessionTable from './SessionTable';

const COLUMNS = 7;

const RekapDashboard = () => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [viewportHeight, setViewportHeight] = useState(window.innerHeight);

  useEffect(() => {
    const handleResize = () => setViewportHeight(window.innerHeight);
    window.addEventListener('resize', handleResize);
    loadData();
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const eventId = window.location.pathname.split('/')[3];
      const response = await HTTP.get(`/events/${eventId}/rekap-peserta`);
      setData(response.data);
    } catch (err) {
      console.error('Error loading rekap peserta:', err);
      setError(err.response?.data?.message || err.message || 'Gagal memuat data rekap peserta');
    } finally {
      setLoading(false);
    }
  };

  const sizing = useMemo(() => {
    if (!data || !data.sesi || data.sesi.length === 0) {
      return null;
    }

    const sesi = data.sesi;
    const sessionCount = sesi.length;
    const maxKuota = Math.max(...sesi.map(s => s.kuota), 24);
    const rowsPerColumn = Math.ceil(sessionCount / COLUMNS);
    const totalTableRows = maxKuota + 3;
    const totalContentRows = totalTableRows * rowsPerColumn;

    const availableHeight = viewportHeight - 16;
    const rowHeight = Math.max(14, Math.floor(availableHeight / totalContentRows));
    const fontSize = Math.max(8, Math.floor(rowHeight * 0.55));
    const headerFontSize = Math.max(9, fontSize + 1);
    const cellPaddingV = Math.max(1, Math.floor(rowHeight * 0.15));
    const cellPaddingH = Math.max(2, Math.floor(rowHeight * 0.25));

    return {
      rowHeight,
      fontSize,
      headerFontSize,
      cellPaddingV,
      cellPaddingH,
      rowsPerColumn,
      maxKuota,
    };
  }, [data, viewportHeight]);

  if (loading) {
    return (
      <div className="rekap-dashboard-page">
        <Spin size="large" style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="rekap-dashboard-page" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh' }}>
        <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={error} />
      </div>
    );
  }

  if (!data || !data.sesi || data.sesi.length === 0) {
    return (
      <div className="rekap-dashboard-page" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh' }}>
        <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Belum ada sesi untuk event ini" />
      </div>
    );
  }

  const { event, sesi } = data;

  if (!sizing) {
    return (
      <div className="rekap-dashboard-page" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh' }}>
        <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Data tidak valid" />
      </div>
    );
  }

  return (
    <div className="rekap-dashboard-page">
      <div className="rekap-dashboard-grid" style={{ gridTemplateColumns: `repeat(${COLUMNS}, 1fr)` }}>
        {sesi.map((s) => (
          <SessionTable
            key={s.id}
            sesi={s}
            isDashboard={true}
            rowHeight={sizing.rowHeight}
            fontSize={sizing.fontSize}
            headerFontSize={sizing.headerFontSize}
            cellPaddingV={sizing.cellPaddingV}
            cellPaddingH={sizing.cellPaddingH}
          />
        ))}
      </div>
    </div>
  );
};

export default RekapDashboard;