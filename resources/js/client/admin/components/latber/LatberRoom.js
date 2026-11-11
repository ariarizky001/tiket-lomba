import React, { useEffect, useState } from 'react';
import { useParams, useHistory } from 'react-router-dom';
import { PageHeader, Tabs, Result, Button, Spin, Row, Col, Statistic } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah, formatAngka } from '../../../common/helpers/Utils';
import LatberSessionTab from './LatberSessionTab';
import SoldTab from './SoldTab';
import OrderedTab from './OrderedTab';

const LatberRoom = () => {
  const { id } = useParams();
  const history = useHistory();
  const [latber, setLatber] = useState(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    fetchLatber();
  }, [id]);

  const fetchLatber = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/latbers/${id}`);
      setLatber(res.data);
      setNotFound(false);
    } catch (error) {
      if (error.response?.status === 404) {
        setNotFound(true);
      }
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <Spin />;
  }

  if (notFound) {
    return (
      <Result
        status="404"
        title="Latber tidak ditemukan"
        subTitle="Latber yang Anda cari tidak ada atau sudah dihapus."
        extra={
          <Button type="primary" onClick={() => history.push('/latbers')}>
            Kembali ke Daftar Latber
          </Button>
        }
      />
    );
  }

  if (!latber) {
    return null;
  }

  const ringkasan = latber.ringkasan || {
    jumlah_sesi: 0,
    total_terjual: 0,
    pemasukan: 0,
    total_terpesan: 0,
    nilai_terpesan: 0,
    pemasukan_terpesan: 0,
  };

  const items = [
    {
      key: 'session',
      label: 'Sesi',
      children: <LatberSessionTab latber={latber} />,
    },
    {
      key: 'sold',
      label: 'Tiket Terjual',
      children: <SoldTab latber={latber} onSessionsUpdate={() => fetchLatber()} />,
    },
    {
      key: 'ordered',
      label: 'Tiket Terpesan',
      children: <OrderedTab latber={latber} onSessionsUpdate={() => fetchLatber()} />,
    },
  ];

  return (
    <div>
      <PageHeader
        onBack={() => history.push('/latbers')}
        title={latber.nama}
        subTitle={`${new Date(latber.tanggal).toLocaleDateString('id-ID')}${latber.lokasi ? ' • ' + latber.lokasi : ''}`}
        style={{ marginBottom: 24 }}
      />

      <Row gutter={16} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={8}>
          <Statistic
            title="Total Tiket Terjual"
            value={ringkasan.total_terjual}
          />
        </Col>
        <Col xs={24} sm={8}>
          <div style={{ marginBottom: 8 }}>
            <Statistic
              title="Pemasukan"
              value={ringkasan.pemasukan}
              formatter={(value) => formatRupiah(value)}
            />
          </div>
          {ringkasan.pemasukan_terpesan > 0 && (
            <div style={{ fontSize: 12, color: '#8c8c8c' }}>
              <div>Terjual: {formatRupiah(ringkasan.pemasukan - ringkasan.pemasukan_terpesan)}</div>
              <div>Terpesan: {formatRupiah(ringkasan.pemasukan_terpesan)}</div>
            </div>
          )}
        </Col>
        <Col xs={24} sm={8}>
          <div style={{ marginBottom: 8 }}>
            <Statistic
              title="Total Tiket Terpesan"
              value={ringkasan.total_terpesan}
            />
          </div>
          {ringkasan.nilai_terpesan > 0 && (
            <div style={{ fontSize: 12, color: '#8c8c8c' }}>
              Nilai: {formatRupiah(ringkasan.nilai_terpesan)} belum dibayar
            </div>
          )}
        </Col>
      </Row>

      <Tabs items={items} />
    </div>
  );
};

export default LatberRoom;
