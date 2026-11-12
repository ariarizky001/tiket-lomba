import React, { useState, useEffect } from 'react';
import { Table, InputNumber, Button, Tag, message, Typography, Space, Grid } from 'antd';
import { formatRupiah } from '../../../common/helpers/Utils';

const { Text } = Typography;

const CountGrid = ({ sessions, onSave, mode, note, loading }) => {
  const screens = Grid.useBreakpoint();
  const isMobile = screens.xs;
  const [counts, setCounts] = useState({});
  const [hasChanges, setHasChanges] = useState(false);

  useEffect(() => {
    const initialCounts = {};
    sessions.forEach((s) => {
      initialCounts[s.id] = mode === 'sold' ? s.terjual : s.terpesan;
    });
    setCounts(initialCounts);
    setHasChanges(false);
  }, [sessions, mode]);

  const handleChange = (id, value) => {
    const numVal = value === null || value === undefined ? 0 : Math.max(0, Math.min(99999, Math.floor(value)));
    const newCounts = { ...counts, [id]: numVal };
    setCounts(newCounts);

    const changed = sessions.some((s) => {
      const serverVal = mode === 'sold' ? s.terjual : s.terpesan;
      return (newCounts[s.id] ?? 0) !== serverVal;
    });
    setHasChanges(changed);
  };

  const handleReset = () => {
    const initialCounts = {};
    sessions.forEach((s) => {
      initialCounts[s.id] = mode === 'sold' ? s.terjual : s.terpesan;
    });
    setCounts(initialCounts);
    setHasChanges(false);
  };

  const handleSave = () => {
    const changedPayload = [];
    sessions.forEach((s) => {
      const serverVal = mode === 'sold' ? s.terjual : s.terpesan;
      const currentVal = counts[s.id] ?? 0;
      if (currentVal !== serverVal) {
        changedPayload.push({
          id: s.id,
          jumlah: currentVal,
        });
      }
    });

    if (changedPayload.length === 0) return;
    onSave(changedPayload);
  };

  const totalJumlah = sessions.reduce((sum, s) => sum + (counts[s.id] ?? 0), 0);
  const totalPemasukan = mode === 'sold'
    ? sessions.reduce((sum, s) => sum + ((counts[s.id] ?? 0) * s.harga), 0)
    : sessions.reduce((sum, s) => sum + ((counts[s.id] ?? 0) * s.harga), 0);

  const columns = [
    {
      title: 'No Urut',
      dataIndex: 'nomor_urut',
      key: 'nomor_urut',
      width: 60,
      align: 'center',
    },
    {
      title: 'Sesi',
      dataIndex: 'nama',
      key: 'nama',
      width: 120,
      fixed: isMobile ? false : 'left',
    },
  ];

  if (mode === 'sold') {
    columns.push({
      title: 'Harga',
      dataIndex: 'harga',
      key: 'harga',
      width: 100,
      render: (harga) => formatRupiah(harga),
    });

    columns.push({
      title: 'Jumlah Terjual',
      key: 'jumlah',
      width: 130,
      render: (_, record) => (
        <InputNumber
          min={0}
          max={99999}
          precision={0}
          value={counts[record.id] ?? 0}
          onChange={(val) => handleChange(record.id, val)}
          style={{ width: '100%' }}
        />
      ),
    });

    columns.push({
      title: 'Pemasukan',
      key: 'pemasukan',
      width: 120,
      render: (_, record) => formatRupiah((counts[record.id] ?? 0) * record.harga),
    });
  } else {
    columns.push({
      title: 'Harga',
      dataIndex: 'harga',
      key: 'harga',
      width: 100,
      render: (harga) => formatRupiah(harga),
    });

    columns.push({
      title: 'Jumlah Terpesan',
      key: 'jumlah',
      width: 130,
      render: (_, record) => (
        <InputNumber
          min={0}
          max={99999}
          precision={0}
          value={counts[record.id] ?? 0}
          onChange={(val) => handleChange(record.id, val)}
          style={{ width: '100%' }}
        />
      ),
    });

    columns.push({
      title: 'Nilai Terpesan',
      key: 'nilai',
      width: 120,
      render: (_, record) => formatRupiah((counts[record.id] ?? 0) * record.harga),
    });
  }

  return (
    <div>
      {note && (
        <div style={{ marginBottom: 16 }}>
          <Text type="secondary">{note}</Text>
        </div>
      )}

      <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap', flexDirection: isMobile ? 'column' : 'row' }}>
        <Button
          type="primary"
          onClick={handleSave}
          disabled={!hasChanges}
          loading={loading}
          block={isMobile}
        >
          Simpan
        </Button>
        <Button onClick={handleReset} disabled={!hasChanges || loading} block={isMobile}>
          Batal Perubahan
        </Button>
        {hasChanges && <Tag color="warning">Ada perubahan belum disimpan</Tag>}
      </div>

      <Table
        columns={columns}
        dataSource={sessions}
        rowKey="id"
        pagination={false}
        scroll={{ x: true }}
        summary={() => (
          <Table.Summary fixed>
            <Table.Summary.Row style={{ fontWeight: 'bold', background: '#fafafa' }}>
              <Table.Summary.Cell index={0} colSpan={2}>
                Total
              </Table.Summary.Cell>
              {mode === 'sold' ? (
                <>
                  <Table.Summary.Cell index={1} />
                  <Table.Summary.Cell index={2}>
                    {totalJumlah}
                  </Table.Summary.Cell>
                  <Table.Summary.Cell index={3}>
                    {formatRupiah(totalPemasukan)}
                  </Table.Summary.Cell>
                </>
              ) : (
                <>
                  <Table.Summary.Cell index={1} />
                  <Table.Summary.Cell index={2}>
                    {totalJumlah}
                  </Table.Summary.Cell>
                  <Table.Summary.Cell index={3}>
                    {formatRupiah(totalPemasukan)}
                  </Table.Summary.Cell>
                </>
              )}
            </Table.Summary.Row>
          </Table.Summary>
        )}
      />
    </div>
  );
};

export default CountGrid;
