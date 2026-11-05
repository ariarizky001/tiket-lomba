import React, { useEffect, useState } from 'react';
import { useParams, useHistory } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { PageHeader, Tabs, Result, Button, Spin } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import SessionTab from '../session/SessionTab';
import InvoiceTab from '../invoice/InvoiceTab';
import ReportTab from '../report/ReportTab';
import ExpenseTab from '../expense/ExpenseTab';
import RekapPeserta from '../rekap/RekapPeserta';
import SpreadsheetConnection from '../spreadsheet/SpreadsheetConnection';
import SheetRangeMappingPanel from '../spreadsheet/SheetRangeMappingPanel';

const EventRoom = () => {
  const { id } = useParams();
  const history = useHistory();
  const user = useSelector((state) => state.auth.user);
  const permissions = useSelector((state) => state.auth.permissions);
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
      permission: 'view_sessions',
    },
    {
      key: 'participant',
      label: 'Peserta & Invoice',
      children: <InvoiceTab event={event} />,
      permission: 'view_invoices',
    },
    {
      key: 'rekap-peserta',
      label: 'Rekap Peserta',
      children: <RekapPeserta eventId={event.id} />,
      permission: 'view_rekap',
    },
    {
      key: 'report',
      label: 'Laporan',
      children: <ReportTab event={event} />,
      permission: 'view_reports',
    },
    {
      key: 'expense',
      label: 'Pengeluaran',
      children: <ExpenseTab eventId={event.id} />,
      permission: 'view_expenses',
    },
    {
      key: 'spreadsheet',
      label: 'Koneksi Spreadsheet',
      children: <SpreadsheetConnection eventId={event.id} />,
      permission: 'manage_spreadsheet',
    },
    {
      key: 'sheet-mapping',
      label: 'Mapping Sheet',
      children: <SheetRangeMappingPanel eventId={event.id} />,
      permission: 'manage_sheet_mapping',
    },
  ];

  const filteredItems = items.filter((item) => {
    if (!item.permission) return true;
    if (user?.role === 'superadmin') return true;
    return permissions.includes(item.permission);
  });

  return (
    <div>
      <PageHeader
        onBack={() => history.push('/events')}
        title={event.nama}
        subTitle={`${new Date(event.tanggal).toLocaleDateString('id-ID')}${event.lokasi ? ' • ' + event.lokasi : ''}`}
        style={{ marginBottom: 24 }}
      />
      <Tabs items={filteredItems} />
    </div>
  );
};

export default EventRoom;
