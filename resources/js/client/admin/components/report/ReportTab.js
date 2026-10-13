import React, { useState, useEffect } from 'react';
import { Spin, Empty, Button, Space, message } from 'antd';
import { ReloadOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import SessionReportTable from './SessionReportTable';
import PrizeGrid from './PrizeGrid';
import ReportSummary from './ReportSummary';

const ReportTab = ({ event }) => {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchReport();
  }, [event.id]);

  const fetchReport = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/events/${event.id}/report`);
      setReport(res.data);
    } catch (error) {
      message.error('Gagal memuat laporan');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <Spin />;
  }

  if (!report || report.sesi.length === 0) {
    return <Empty description="Belum ada sesi" />;
  }

  return (
    <div>
      <div style={{ marginBottom: 24, textAlign: 'right' }}>
        <Button icon={<ReloadOutlined />} onClick={fetchReport}>
          Muat Ulang
        </Button>
      </div>

      <SessionReportTable sesi={report.sesi} />

      <div style={{ marginTop: 32, marginBottom: 32 }}>
        <h3>Rincian Hadiah per Sesi</h3>
        <p style={{ color: '#999', fontSize: 12 }}>Opsional, boleh dikosongkan untuk sesi yang tidak ada hadiahnya</p>
        <PrizeGrid sesi={report.sesi} event={event} onRefresh={fetchReport} />
      </div>

      <ReportSummary ringkasan={report.ringkasan} />
    </div>
  );
};

export default ReportTab;
