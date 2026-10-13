import React, { useMemo } from 'react';
import { Table, Tag, Empty, Space } from 'antd';

const ParticipantTable = ({ peserta, summary, statusFilter, searchText, loading }) => {
  const filtered = useMemo(() => {
    let result = peserta;

    if (statusFilter !== 'all') {
      result = result.filter(p => p.status === statusFilter);
    }

    if (searchText) {
      result = result.filter(p =>
        p.nama_peserta.toLowerCase().includes(searchText.toLowerCase())
      );
    }

    return result;
  }, [peserta, statusFilter, searchText]);

  // Count occurrences of each peserta in original data to determine if prefix needed
  const pesertaCount = useMemo(() => {
    const counts = {};
    peserta.forEach(p => {
      counts[p.nama_peserta] = (counts[p.nama_peserta] || 0) + 1;
    });
    return counts;
  }, [peserta]);

const columns = [
    {
      title: 'No',
      width: 10px,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Nama Peserta',
      dataIndex: 'nama_peserta',
      key: 'nama_peserta',
      width: 20,
      render: (text, record) => {
        return pesertaCount[text] > 1 ? `${text} #${record.urutan}` : text;
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 20,
      align: 'center',
      render: (status) => (
        <Tag color={status === 'lunas' ? 'success' : 'warning'}>
          {status === 'lunas' ? 'Lunas' : 'Belum Lunas'}
        </Tag>
      ),
    },
  ];

  if (filtered.length === 0 && !loading) {
    return (
      <div style={{ textAlign: 'center', padding: '40px 20px', color: '#999' }}>
        Tidak ada data yang sesuai
      </div>
    );
  }

  return (
    <Table
      columns={columns}
      dataSource={filtered}
      loading={loading}
      rowKey={(_, index) => index}
      pagination={false}
      bordered
      size="middle"
      locale={{ emptyText: 'Tidak ada data peserta' }}
    />
  );
};

export default ParticipantTable;
