import React, { useEffect, useState } from 'react';
import { Table, Button, Drawer, Form, InputNumber, Input, Popconfirm, message, Empty } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';

const SessionTab = ({ event }) => {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form] = Form.useForm();

  const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  });

  useEffect(() => {
    fetchSessions();
  }, [event.id]);

  const fetchSessions = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/events/${event.id}/sessions`);
      setSessions(res.data);
    } catch (error) {
      message.error('Gagal memuat sesi');
    } finally {
      setLoading(false);
    }
  };

  const openDrawer = (session = null) => {
    if (session) {
      setEditing(session);
      form.setFieldsValue(session);
    } else {
      setEditing(null);
      form.resetFields();
      form.setFieldValue('kuota', 24);
    }
    setDrawerVisible(true);
  };

  const onFinish = async (values) => {
    try {
      if (editing) {
        await HTTP.put(`/sessions/${editing.id}`, values);
        message.success('Sesi diperbarui');
      } else {
        await HTTP.post(`/events/${event.id}/sessions`, values);
        message.success('Sesi ditambahkan');
      }

      setDrawerVisible(false);
      fetchSessions();
    } catch (error) {
      const errors = error.response?.data?.errors;
      if (errors) {
        Object.keys(errors).forEach((field) => {
          form.setFields([
            {
              name: field,
              errors: errors[field],
            },
          ]);
        });
      } else {
        message.error('Gagal menyimpan sesi');
      }
    }
  };

  const handleDelete = async (id) => {
    try {
      await HTTP.delete(`/sessions/${id}`);
      message.success('Sesi dihapus');
      fetchSessions();
    } catch (error) {
      message.error('Gagal menghapus sesi');
    }
  };

  const columns = [
    {
      title: 'No',
      width: 50,
      render: (_, __, index) => index + 1,
    },
    {
      title: 'Nama Sesi',
      dataIndex: 'nama',
      key: 'nama',
    },
    {
      title: 'Harga',
      dataIndex: 'harga',
      key: 'harga',
      render: (harga) => rupiah.format(harga),
    },
    {
      title: 'Kuota',
      dataIndex: 'kuota',
      key: 'kuota',
      width: 60,
    },
    {
      title: 'Terjual',
      dataIndex: 'terjual',
      key: 'terjual',
      width: 70,
      render: (terjual) => terjual || 0,
    },
    {
      title: 'Sisa',
      dataIndex: 'sisa',
      key: 'sisa',
      width: 60,
      render: (sisa) => sisa || 0,
    },
    {
      title: 'Aksi',
      key: 'aksi',
      width: 150,
      render: (_, record) => (
        <div style={{ display: 'flex', gap: 8 }}>
          <Button size="small" onClick={() => openDrawer(record)}>
            Edit
          </Button>
          <Popconfirm
            title="Hapus Sesi"
            description="Apakah Anda yakin ingin menghapus sesi ini?"
            onConfirm={() => handleDelete(record.id)}
            okText="Ya"
            cancelText="Batal"
          >
            <Button size="small" danger>
              Hapus
            </Button>
          </Popconfirm>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>
          Tambah Sesi
        </Button>
      </div>

      {sessions.length === 0 ? (
        <Empty description="Belum ada sesi, tambahkan sesi pertama" />
      ) : (
        <Table
          columns={columns}
          dataSource={sessions}
          loading={loading}
          rowKey="id"
          scroll={{ x: true }}
          pagination={false}
        />
      )}

      <Drawer
        title={editing ? 'Edit Sesi' : 'Tambah Sesi'}
        placement="right"
        onClose={() => setDrawerVisible(false)}
        open={drawerVisible}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={onFinish}
        >
          <Form.Item
            label="Nama Sesi"
            name="nama"
            rules={[
              { required: true, message: 'Nama sesi wajib diisi' },
              { max: 150, message: 'Nama sesi maksimal 150 karakter' },
            ]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label="Harga"
            name="harga"
            rules={[
              { required: true, message: 'Harga wajib diisi' },
              { type: 'number', min: 0, message: 'Harga tidak boleh negatif' },
            ]}
          >
            <InputNumber style={{ width: '100%' }} step={1000} min={0} />
          </Form.Item>
          <Form.Item
            label="Kuota"
            name="kuota"
            rules={[
              { required: true, message: 'Kuota wajib diisi' },
              { type: 'number', min: 1, max: 1000, message: 'Kuota harus antara 1-1000' },
            ]}
          >
            <InputNumber style={{ width: '100%' }} min={1} max={1000} />
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" block>
              Simpan
            </Button>
          </Form.Item>
        </Form>
      </Drawer>
    </div>
  );
};

export default SessionTab;
