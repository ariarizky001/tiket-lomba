import React, { useState, useEffect } from 'react';
import { Spin, Empty, message, Button, Typography } from 'antd';
import { ReloadOutlined, FullscreenOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import RekapBoard from './RekapBoard';

const { Title, Text } = Typography;

const RekapPeserta = ({ eventId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadData();
  }, [eventId]);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await HTTP.get(`/events/${eventId}/rekap-peserta`);
      setData(response.data);
    } catch (err) {
      console.error('Error loading rekap peserta:', err);
      setError(err.response?.data?.message || err.message || 'Gagal memuat data rekap peserta');
      message.error('Gagal memuat data rekap peserta');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenFullscreen = () => {
    window.open(`/admin/events/${eventId}/rekap-display`, '_blank', 'noopener,noreferrer');
  };

  if (loading) {
    return <Spin size="large" style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }} />;
  }

  if (error) {
    return (
      <Empty
        image={Empty.PRESENTED_IMAGE_SIMPLE}
        description={error}
      />
    );
  }

  if (!data || !data.sesi || data.sesi.length === 0) {
    return (
      <Empty
        image={Empty.PRESENTED_IMAGE_SIMPLE}
        description="Belum ada sesi untuk event ini"
      />
    );
  }

  const { event, sesi } = data;

  return (
    <div className="h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between mb-4 no-print">
        <div>
          <Title level={4} style={{ margin: 0 }}>
            {event?.nama || 'Event'}
          </Title>
          <Text className="text-gray-500 text-sm">
            {event?.tanggal ? new Date(event.tanggal).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '-'}
            {event?.lokasi && ` - ${event.lokasi}`}
          </Text>
        </div>
        <div className="flex items-center gap-2">
          <Button icon={<ReloadOutlined />} onClick={loadData} loading={loading}>
            Muat Ulang
          </Button>
          <Button icon={<FullscreenOutlined />} onClick={handleOpenFullscreen}>
            Layar Penuh
          </Button>
        </div>
      </div>

      {/* Board */}
      <div className="rekap-grid-container">
        <RekapBoard sesi={sesi} />
      </div>
    </div>
  );
};

export default RekapPeserta;Di
