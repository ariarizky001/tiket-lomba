import React, { useState, useRef, useEffect } from 'react';
import { Table, InputNumber, Button, message, Tag } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah } from '../../../common/helpers/Utils';

const PrizeGrid = ({ sesi, event, onRefresh }) => {
  const [localState, setLocalState] = useState({});
  const [changed, setChanged] = useState(false);
  const [saving, setSaving] = useState(false);
  const initialStateRef = useRef({});

  useEffect(() => {
    const state = {};
    sesi.forEach((s) => {
      state[s.id] = { 1: null, 2: null, 3: null, 4: null, 5: null };
      (s.prizes || []).forEach((p) => {
        state[s.id][p.juara] = p.nominal;
      });
    });
    initialStateRef.current = state;
    setLocalState({ ...state });
    setChanged(false);
  }, [sesi]);

  const handleChange = (sessionId, juara, value) => {
    setLocalState((prev) => {
      const newState = { ...prev, [sessionId]: { ...prev[sessionId], [juara]: value || null } };
      const isChanged = JSON.stringify(newState) !== JSON.stringify(initialStateRef.current);
      setChanged(isChanged);
      return newState;
    });
  };

  const calculateRowTotal = (sessionId) => {
    const row = localState[sessionId] || {};
    return Object.values(row).reduce((sum, n) => sum + (n || 0), 0);
  };

  const calculateColTotal = (juara) => {
    return Object.values(localState).reduce((sum, row) => sum + (row[juara] || 0), 0);
  };

  const calculateGrandTotal = () => {
    return Object.values(localState).reduce((sum, row) => {
      return sum + Object.values(row).reduce((rSum, n) => rSum + (n || 0), 0);
    }, 0);
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const payload = {
        sesi: sesi.map((s) => ({
          event_session_id: s.id,
          juara: localState[s.id] || {},
        })),
      };
      const res = await HTTP.put(`/events/${event.id}/prizes`, payload);
      
      message.success('Hadiah tersimpan');
      onRefresh(res.data);
      setLocalState({ ...initialStateRef.current });
      setChanged(false);
    } catch (error) {
      if (error.response?.data?.errors) {
        let errorMsg = '';
        Object.keys(error.response.data.errors).forEach((key) => {
          errorMsg += `${key}: ${error.response.data.errors[key].join(', ')}\n`;
        });
        message.error(errorMsg || 'Gagal menyimpan hadiah');
      } else {
        message.error(error.response?.data?.message || 'Gagal menyimpan hadiah');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    setLocalState({ ...initialStateRef.current });
    setChanged(false);
  };

  const columns = [
    {
      title: 'Nama Sesi',
      dataIndex: 'nama',
      key: 'nama',
      width: 200,
      fixed: 'left',
      render: (text, record) => (
        <div style={{ fontWeight: 'bold' }}>{record.nomor_urut ? `${record.nomor_urut}. ` : ''}{text}</div>
      ),
    },
    ...[1, 2, 3, 4, 5].map((juara) => ({
      title: `Juara ${juara}`,
      key: `juara_${juara}`,
      width: 120,
      render: (_, record) => (
        <InputNumber
          style={{ width: '100%' }}
          min={0}
          max={999999999}
          step={50000}
          placeholder="-"
          controls={false}
          value={localState[record.id]?.[juara] || ''}
          onChange={(val) => handleChange(record.id, juara, val)}
        />
      ),
    })),
    {
      title: 'Total',
      key: 'total',
      width: 100,
      render: (_, record) => formatRupiah(calculateRowTotal(record.id)),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
        {changed && <Tag color="warning">Ada perubahan belum disimpan</Tag>}
        <Button
          type="primary"
          onClick={handleSave}
          disabled={!changed || saving}
          loading={saving}
        >
          Simpan Hadiah
        </Button>
        {changed && (
          <Button onClick={handleCancel} disabled={saving}>Batal Perubahan</Button>
        )}
      </div>

      <Table
        columns={columns}
        dataSource={sesi}
        rowKey="id"
        pagination={false}
        scroll={{ x: true }}
        size="small"
        summary={() => (
          <Table.Summary fixed>
            <Table.Summary.Row>
              <Table.Summary.Cell index={0} style={{ fontWeight: 'bold' }}>Total</Table.Summary.Cell>
              {[1, 2, 3, 4, 5].map((juara) => (
                <Table.Summary.Cell key={juara} index={juara}>
                  {formatRupiah(calculateColTotal(juara))}
                </Table.Summary.Cell>
              ))}
              <Table.Summary.Cell index={6} style={{ fontWeight: 'bold' }}>
                {formatRupiah(calculateGrandTotal())}
              </Table.Summary.Cell>
            </Table.Summary.Row>
          </Table.Summary>
        )}
      />
    </div>
  );
};

export default PrizeGrid;
