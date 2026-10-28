import React, { useState, useEffect } from 'react';
import { Form, Input, Button, message, Spin, Table, Modal, Typography, Tag, Space } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, ReloadOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';

const { Title, Text } = Typography;

const SheetRangeMappingPanel = ({ eventId }) => {
  const [mappings, setMappings] = useState([]);
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [formLoading, setFormLoading] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingMapping, setEditingMapping] = useState(null);
  const [form] = Form.useForm();

  useEffect(() => {
    loadData();
  }, [eventId]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [mappingsRes, sessionsRes] = await Promise.all([
        HTTP.get(`/events/${eventId}/sheet-mappings`),
        HTTP.get(`/events/${eventId}/sessions`),
      ]);
      setMappings(mappingsRes.data.mappings || []);
      // Sort sessions by ID and add urutan (1-based index)
      const sortedSessions = (sessionsRes.data || []).sort((a, b) => a.id - b.id);
      const sessionsWithUrutan = sortedSessions.map((s, i) => ({ ...s, urutan: i + 1 }));
      setSessions(sessionsWithUrutan);
    } catch (err) {
      message.error('Gagal memuat data mapping');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (mapping = null) => {
    if (mapping) {
      setEditingMapping(mapping);
      form.setFieldsValue({
        session_id: mapping.session_id,
        session_index: mapping.session_index,
        sheet_range: mapping.sheet_range,
        sheet_name: mapping.sheet_name,
      });
    } else {
      setEditingMapping(null);
      form.resetFields();
      form.setFieldsValue({
        sheet_name: 'Daftar Peserta',
      });
    }
    setIsModalVisible(true);
  };

  const handleCloseModal = () => {
    setIsModalVisible(false);
    setEditingMapping(null);
    form.resetFields();
  };

  const handleSubmit = async (values) => {
    setFormLoading(true);
    try {
      if (editingMapping) {
        await HTTP.put(`/events/${eventId}/sheet-mappings/${editingMapping.id}`, values);
        message.success('Mapping diperbarui');
      } else {
        await HTTP.post(`/events/${eventId}/sheet-mappings`, values);
        message.success('Mapping ditambahkan');
      }
      handleCloseModal();
      loadData();
    } catch (err) {
      message.error(err.response?.data?.message || 'Gagal menyimpan mapping');
    } finally {
      setFormLoading(false);
    }
  };

  const handleDelete = (mapping) => {
    Modal.confirm({
      title: 'Hapus Mapping',
      content: `Hapus mapping untuk sesi "${mapping.session_name}" (${mapping.sheet_range})?`,
      okText: 'Hapus',
      okType: 'danger',
      cancelText: 'Batal',
      onOk: async () => {
        try {
          await HTTP.delete(`/events/${eventId}/sheet-mappings/${mapping.id}`);
          message.success('Mapping dihapus');
          loadData();
        } catch (err) {
          message.error(err.response?.data?.message || 'Gagal menghapus mapping');
        }
      },
    });
  };

  const handleBulkSave = async () => {
    const unmappedSessions = sessions.filter(s => !mappings.find(m => m.session_id === s.id));
    if (unmappedSessions.length === 0) {
      message.info('Semua sesi sudah memiliki mapping');
      return;
    }

    const defaultMappings = unmappedSessions.map((session) => {
      const baseCol = ['C', 'J', 'Q', 'X', 'AE', 'AL', 'AS'];
      const colIndex = (session.urutan - 1) % baseCol.length;
      const rowOffset = Math.floor((session.urutan - 1) / baseCol.length) * 27;
      const startRow = 4 + rowOffset;
      const endRow = 27 + rowOffset;
      const sheetRange = `${baseCol[colIndex]}${startRow}:${baseCol[colIndex]}${endRow}`;

      return {
        session_id: session.id,
        session_index: session.urutan,
        sheet_range: sheetRange,
        sheet_name: 'Daftar Peserta',
      };
    });

    setFormLoading(true);
    try {
      await HTTP.post(`/events/${eventId}/sheet-mappings/bulk`, { mappings: defaultMappings });
      message.success(`${defaultMappings.length} mapping default ditambahkan`);
      loadData();
    } catch (err) {
      message.error(err.response?.data?.message || 'Gagal menambah mapping default');
    } finally {
      setFormLoading(false);
    }
  };

  const columns = [
    {
      title: 'Sesi',
      dataIndex: 'session_name',
      key: 'session_name',
      width: 200,
    },
    {
      title: 'Index',
      dataIndex: 'session_index',
      key: 'session_index',
      width: 80,
      align: 'center',
    },
    {
      title: 'Sheet Range',
      dataIndex: 'sheet_range',
      key: 'sheet_range',
      width: 150,
      render: (range) => <Tag color="blue">{range}</Tag>,
    },
    {
      title: 'Sheet Name',
      dataIndex: 'sheet_name',
      key: 'sheet_name',
      width: 150,
    },
    {
      title: 'Aksi',
      key: 'actions',
      width: 120,
      render: (_, record) => (
        <Space>
          <Button type="link" icon={<EditOutlined />} onClick={() => handleOpenModal(record)} size="small">
            Edit
          </Button>
          <Button type="link" icon={<DeleteOutlined />} danger onClick={() => handleDelete(record)} size="small">
            Hapus
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <div className="p-4">
      <div className="flex items-center justify-between mb-4">
        <Title level={4}>Mapping Sesi ke Range Spreadsheet</Title>
        <div className="flex gap-2">
          <Button icon={<ReloadOutlined />} onClick={loadData} loading={loading}>
            Muat Ulang
          </Button>
          <Button icon={<PlusOutlined />} onClick={() => handleOpenModal()} type="primary">
            Tambah Mapping
          </Button>
          <Button icon={<PlusOutlined />} onClick={handleBulkSave} loading={formLoading}>
            Generate Default
          </Button>
        </div>
      </div>

      <div className="mb-4 text-sm text-gray-600">
        <p>Mapping menentukan ke mana data nama peserta dikirim di spreadsheet.</p>
        <p>Format range: <code>C4:C27</code>, <code>J4:J27</code>, <code>AE4:AE27</code>, dll.</p>
        <p>Klik <strong>Generate Default</strong> untuk membuat mapping otomatis berdasarkan pola standar.</p>
      </div>

      {loading ? (
        <Spin />
      ) : (
        <>
          <Table
            columns={columns}
            dataSource={mappings}
            rowKey="id"
            pagination={false}
            bordered
            size="small"
          />
          {mappings.length === 0 && (
            <div className="text-center py-8 text-gray-500">
              Belum ada mapping. Klik "Tambah Mapping" atau "Generate Default".
            </div>
          )}
        </>
      )}

      <Modal
        title={editingMapping ? 'Edit Mapping' : 'Tambah Mapping'}
        open={isModalVisible}
        onCancel={handleCloseModal}
        footer={[
          <Button key="cancel" onClick={handleCloseModal}>
            Batal
          </Button>,
          <Button key="submit" type="primary" loading={formLoading} onClick={() => form.validateFields().then(handleSubmit).catch(() => {})}>
            {editingMapping ? 'Simpan Perubahan' : 'Tambah'}
          </Button>,
        ]}
      >
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
          <Form.Item
            label="Sesi"
            name="session_id"
            rules={[{ required: true, message: 'Pilih sesi' }]}
          >
            <Form.Item.Input
              options={sessions.map(s => ({ label: `${s.nomor_urut}. ${s.nama}`, value: s.id }))}
              placeholder="Pilih sesi"
            />
          </Form.Item>

          <Form.Item
            label="Index Sesi"
            name="session_index"
            rules={[{ required: true, message: 'Index sesi diperlukan' }]}
          >
            <Input.Number min={1} placeholder="1, 2, 3..." />
          </Form.Item>

          <Form.Item
            label="Sheet Range"
            name="sheet_range"
            rules={[{ required: true, message: 'Range diperlukan (contoh: C4:C27)' }]}
          >
            <Input placeholder="C4:C27" />
          </Form.Item>

          <Form.Item
            label="Sheet Name"
            name="sheet_name"
          >
            <Input placeholder="Daftar Peserta" />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default SheetRangeMappingPanel;