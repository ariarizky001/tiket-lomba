import React, { useEffect, useState } from 'react';
import { useParams, useHistory } from 'react-router-dom';
import { PageHeader, Tabs, Result, Button, Spin } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import SessionTab from '../session/SessionTab';
import InvoiceTab from '../invoice/InvoiceTab';

const EventRoom = () => {
  const { id } = useParams();
  const history = useHistory();
  const [event, setEvent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    fetchEvent();
  }, [id]);

  const fetchEvent = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/events/${id}`);
      setEvent(res.data);
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
        title="Event tidak ditemukan"
        subTitle="Event yang Anda cari tidak ada atau sudah dihapus."
        extra={
          <Button type="primary" onClick={() => history.push('/events')}>
            Kembali ke Daftar Event
          </Button>
        }
      />
    );
  }

  if (!event) {
    return null;
  }

  const items = [
    {
      key: 'session',
      label: 'Sesi',
      children: <SessionTab event={event} />,
    },
    {
      key: 'participant',
      label: 'Peserta & Invoice',
      children: <InvoiceTab event={event} />,
    },
    {
      key: 'prize',
      label: 'Hadiah',
      disabled: true,
      children: null,
    },
    {
      key: 'report',
      label: 'Laporan',
      disabled: true,
      children: null,
    },
  ];

  return (
    <div>
      <PageHeader
        onBack={() => history.push('/events')}
        title={event.nama}
        subTitle={`${new Date(event.tanggal).toLocaleDateString('id-ID')}${event.lokasi ? ' • ' + event.lokasi : ''}`}
        style={{ marginBottom: 24 }}
      />
      <Tabs items={items} />
    </div>
  );
};

export default EventRoom;
