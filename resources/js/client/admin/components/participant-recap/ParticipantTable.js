import React, { useMemo } from 'react';
import { Table, Tag, Empty } from 'antd';

const ParticipantTable = ({ peserta, loading }) => {
  const filtered = useMemo(() => {
    let result = peserta;

    // Count occurrences of each peserta in original data to determine if prefix needed
    const pesertaCount = {};
    peserta.forEach(p => {
      pesertaCount[p.nama_peserta] = (pesertaCount[p.nama_peserta] || 0) + 1;
    });

    // Add prefix if qty > 1
    return result.map(p => ({
      ...p,
      displayName: pesertaCount[p.nama_peserta] > 1 ? `${p.nama_peserta} #${p.urutan}` : p.nama_peserta,
    }));
  }, [peserta]);

  const columns = [
    {
      title: 'No',
      width: 60,
      align: 'center',
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Nama Peserta',
      dataIndex: 'displayName',
      key: 'nama_peserta',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      align: 'center',
      render: (status) => (
        <Tag color={status === 'lunas' ? 'default' : 'red'}>
          {status === 'lunas' ? 'Lunas' : 'Belum Lunas'}
        </Tag>
      ),
    },
  ];

  if (filtered.length === 0 && !loading) {
    return (
      <Empty description="Tidak ada data peserta" style={{ padding: 20 }} />
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
    />
  );
};

export default ParticipantTable;
