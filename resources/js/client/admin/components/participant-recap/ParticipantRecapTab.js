import React, { useState, useEffect } from 'react';
import { Spin, Empty, message, Row, Col, Tag, Typography, Card } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import ParticipantTable from './ParticipantTable';

const ParticipantRecapTab = ({ eventId }) => {
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
      const response = await HTTP.get(`/events/${eventId}/participant-recap`);
      setData(response.data);
    } catch (err) {
      console.error('Error loading participant recap:', err);
      setError(err.response?.data?.message || err.message || 'Gagal memuat data peserta');
      message.error('Gagal memuat data peserta');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <Spin />;
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
        description="Belum ada data peserta"
      />
    );
  }

  return (
    <Row gutter={[16, 16]}>
      {data.sesi.map((sesi) => {
        const summary = sesi.summary;
        const totalPeserta = summary.total_peserta;

        return (
          <Col key={sesi.id} xs={24} sm={24} md={12} lg={6} style={{ marginBottom: 16 }}>
            <Card size="small" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
              <div style={{ marginBottom: 12, paddingBottom: 8, borderBottom: '1px solid #f0f0f0' }}>
                <Typography.Title level={5} style={{ margin: 0, display: 'inline' }}>
                  {sesi.nama}
                </Typography.Title>
                <Tag color="blue" style={{ marginLeft: 8, fontSize: 11 }}>
                  {totalPeserta} Peserta
                </Tag>
              </div>
              <div style={{ flex: 1 }}>
                <ParticipantTable peserta={sesi.peserta} loading={loading} />
              </div>
            </Card>
          </Col>
        );
      })}
    </Row>
  );
};

export default ParticipantRecapTab;