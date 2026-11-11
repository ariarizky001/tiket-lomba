import React, { useState, useEffect } from 'react';
import { Table, Button, InputNumber, Modal, message, Typography, Tag, Tooltip } from 'antd';
import { DollarOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah } from '../../../common/helpers/Utils';

const { Text } = Typography;

const OrderedTab = ({ latber, onSessionsUpdate }) => {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [terpesanCounts, setTerpesanCounts] = useState({});
  const [hasChanges, setHasChanges] = useState(false);
  const [ambilModalVisible, setAmbilModalVisible] = useState(false);
  const [selectedSession, setSelectedSession] = useState(null);
  const [ambilJumlah, setAmbilJumlah] = useState(0);
  const [ambilLoading, setAmbilLoading] = useState(false);

  useEffect(() => {
    fetchSessions();
  }, [latber.id]);

  useEffect(() => {
    if (sessions.length > 0) {
      const initialCounts = {};
      sessions.forEach((s) => {
        initialCounts[s.id] = s.terpesan;
      });
      setTerpesanCounts(initialCounts);
      setHasChanges(false);
    }
  }, [sessions]);

  const fetchSessions = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/latbers/${latber.id}/sessions`);
      setSessions(res.data);
    } catch (error) {
      message.error('Gagal memuat data sesi');
    } finally {
      setLoading(false);
    }
  };

  const handleTerpesanChange = (id, value) => {
    const numVal = value === null || value === undefined ? 0 : Math.max(0, Math.min(99999, Math.floor(value)));
    const newCounts = { ...terpesanCounts, [id]: numVal };
    setTerpesanCounts(newCounts);

    const changed = sessions.some((s) => (newCounts[s.id] ?? 0) !== s.terpesan);
    setHasChanges(changed);
  };

  const handleReset = () => {
    const initialCounts = {};
    sessions.forEach((s) => {
      initialCounts[s.id] = s.terpesan;
    });
    setTerpesanCounts(initialCounts);
    setHasChanges(false);
  };

  const handleSaveTerpesan = async () => {
    const changedPayload = [];
    sessions.forEach((s) => {
      const currentVal = terpesanCounts[s.id] ?? 0;
      if (currentVal !== s.terpesan) {
        changedPayload.push({
          id: s.id,
          jumlah: currentVal,
        });
      }
    });

    if (changedPayload.length === 0) return;

    setLoading(true);
    try {
      const res = await HTTP.put(`/latbers/${latber.id}/terpesan`, {
        sesi: changedPayload,
      });
      message.success('Tersimpan');
      setSessions(res.data);
      setTerpesanCounts({});
      setHasChanges(false);
      if (onSessionsUpdate) {
        onSessionsUpdate(res.data);
      }
    } catch (error) {
      const msg = error.response?.data?.message || 'Gagal menyimpan data';
      message.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const openAmbilModal = (session) => {
    setSelectedSession(session);
    setAmbilJumlah(0);
    setAmbilModalVisible(true);
  };

  const handleAmbilConfirm = async () => {
    if (!selectedSession || ambilJumlah <= 0) {
      message.error('Jumlah harus lebih dari 0');
      return;
    }

    if (ambilJumlah > selectedSession.terpesan) {
      message.error('Jumlah melebihi tiket terpesan yang tersedia');
      return;
    }

    setAmbilLoading(true);
    try {
      const res = await HTTP.put(`/latbers/${latber.id}/terpesan/ambil`, {
        session_id: selectedSession.id,
        jumlah: ambilJumlah,
      });
      message.success('Tiket terpesan berhasil ditandai sebagai sudah diambil');
      setSessions(res.data);
      setAmbilModalVisible(false);
      if (onSessionsUpdate) {
        onSessionsUpdate(res.data);
      }
    } catch (error) {
      const msg = error.response?.data?.message || 'Gagal memproses pengambilan';
      message.error(msg);
    } finally {
      setAmbilLoading(false);
    }
  };

  const columns = [
    {
      title: 'No Urut',
      dataIndex: 'nomor_urut',
      key: 'nomor_urut',
      width: 80,
      align: 'center',
    },
    {
      title: 'Sesi',
      dataIndex: 'nama',
      key: 'nama',
      fixed: 'left',
    },
    {
      title: 'Harga',
      dataIndex: 'harga',
      key: 'harga',
      render: (harga) => formatRupiah(harga),
    },
    {
      title: 'Jumlah Terpesan',
      key: 'jumlah',
      width: 150,
      align: 'center',
      render: (_, record) => (
        <InputNumber
          min={0}
          max={99999}
          precision={0}
          value={terpesanCounts[record.id] ?? record.terpesan}
          onChange={(val) => handleTerpesanChange(record.id, val)}
          style={{ width: '100%' }}
          disabled={loading}
        />
      ),
    },
    {
      title: 'Nilai Terpesan',
      key: 'nilai',
      render: (_, record) => {
        const count = terpesanCounts[record.id] ?? record.terpesan;
        return formatRupiah(count * record.harga);
      },
    },
    {
      title: 'Aksi',
      key: 'aksi',
      width: 180,
      render: (_, record) => (
        <div style={{ display: 'flex', gap: 8, justifyContent: 'center' }}>
          <Tooltip title="Tandai sudah diambil (pembayaran)" placement="top">
            <Button
              type="primary"
              icon={<DollarOutlined />}
              onClick={() => openAmbilModal(record)}
              disabled={record.terpesan === 0 || loading}
            >
              Tandai Diambil
            </Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  const totalTerpesan = sessions.reduce((sum, s) => sum + (terpesanCounts[s.id] ?? s.terpesan), 0);
  const totalNilai = sessions.reduce((sum, s) => sum + ((terpesanCounts[s.id] ?? s.terpesan) * s.harga), 0);

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <Text type="secondary">Tiket terpesan belum dihitung sebagai pemasukan. Edit jumlah lalu klik Simpan.</Text>
        <Button
          type="primary"
          onClick={handleSaveTerpesan}
          disabled={!hasChanges || loading}
          loading={loading}
        >
          Simpan Perubahan
        </Button>
        <Button onClick={handleReset} disabled={!hasChanges || loading}>
          Batal
        </Button>
        {hasChanges && <Tag color="warning">Ada perubahan belum disimpan</Tag>}
      </div>

      <Table
        columns={columns}
        dataSource={sessions}
        rowKey="id"
        loading={loading}
        pagination={false}
        scroll={{ x: true }}
        summary={() => (
          <Table.Summary fixed>
            <Table.Summary.Row style={{ fontWeight: 'bold', background: '#fafafa' }}>
              <Table.Summary.Cell index={0} colSpan={3}>
                Total
              </Table.Summary.Cell>
              <Table.Summary.Cell index={1}>
                {totalTerpesan}
              </Table.Summary.Cell>
              <Table.Summary.Cell index={2}>
                {formatRupiah(totalNilai)}
              </Table.Summary.Cell>
              <Table.Summary.Cell index={3} />
            </Table.Summary.Row>
          </Table.Summary>
        )}
      />

      <Modal
        title={`Tandai Terpesan Sudah Diambil - ${selectedSession?.nama || ''}`}
        open={ambilModalVisible}
        onOk={handleAmbilConfirm}
        onCancel={() => setAmbilModalVisible(false)}
        confirmLoading={ambilLoading}
        okText="Konfirmasi"
        cancelText="Batal"
      >
        {selectedSession && (
          <div>
            <p><strong>Tiket Terpesan Tersedia:</strong> {selectedSession.terpesan}</p>
            <p><strong>Harga per Tiket:</strong> {formatRupiah(selectedSession.harga)}</p>
            <div style={{ marginTop: 16 }}>
              <label><strong>Jumlah yang Diambil:</strong></label>
              <InputNumber
                style={{ width: '100%', marginTop: 8 }}
                min={1}
                max={selectedSession.terpesan}
                precision={0}
                value={ambilJumlah}
                onChange={setAmbilJumlah}
              />
            </div>
            {ambilJumlah > 0 && (
              <div style={{ marginTop: 16, padding: 12, background: '#f0f2f5', borderRadius: 4 }}>
                <Text>
                  Menandai <strong>{ambilJumlah}</strong> tiket terpesan sebagai sudah diambil akan menambah pemasukan sebesar{' '}
                  <strong style={{ color: '#52c41a' }}>
                    {formatRupiah(ambilJumlah * selectedSession.harga)}
                  </strong>
                </Text>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
};

export default OrderedTab;