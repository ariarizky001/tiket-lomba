import React, { useEffect, useState } from 'react';
import { Table, Button, Drawer, Form, DatePicker, Input, Switch, Popconfirm, message, Space, Tag, Grid } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SettingOutlined } from '@ant-design/icons';
import { useHistory } from 'react-router-dom';
import HTTP from '../../../../common/helpers/HTTP';
import dayjs from 'dayjs';

const EventList = () => {
  const history = useHistory();
  const screens = Grid.useBreakpoint();
  const isMobile = screens.xs;
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form] = Form.useForm();

  useEffect(() => {
    fetchEvents();
  }, []);

  const fetchEvents = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get('/events');
      setEvents(res.data);
    } catch (error) {
      message.error('Gagal memuat event');
    } finally {
      setLoading(false);
    }
  };

  const openDrawer = (event = null) => {
    if (event) {
      setEditing(event);
      form.setFieldsValue({
        ...event,
        tanggal: dayjs(event.tanggal),
      });
    } else {
      setEditing(null);
      form.resetFields();
    }
    setDrawerVisible(true);
  };

  const onFinish = async (values) => {
    try {
      const data = {
        ...values,
        tanggal: values.tanggal.format('YYYY-MM-DD'),
      };

      if (editing) {
        await HTTP.put(`/events/${editing.id}`, data);
        message.success('Event diperbarui');
      } else {
        await HTTP.post('/events', data);
        message.success('Event ditambahkan');
      }

      setDrawerVisible(false);
      fetchEvents();
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
        message.error('Gagal menyimpan event');
      }
    }
  };

  const handleDelete = async (id) => {
    try {
      await HTTP.delete(`/events/${id}`);
      message.success('Event dihapus');
      fetchEvents();
    } catch (error) {
      message.error('Gagal menghapus event');
    }
  };

  const columns = [
    { 
      title: 'Nama', 
      dataIndex: 'nama', 
      key: 'nama',
      width: 150,
      fixed: isMobile ? false : 'left',
    },
    { 
      title: 'Tanggal', 
      dataIndex: 'tanggal', 
      key: 'tanggal', 
      width: 100,
      render: (text) => new Date(text).toLocaleDateString('id-ID') 
    },
    { 
      title: 'Lokasi', 
      dataIndex: 'lokasi', 
      key: 'lokasi',
      width: 120,
      className: isMobile ? 'ant-table-column-hidden-xs' : '',
    },
    { 
      title: 'Status', 
      dataIndex: 'is_active', 
      key: 'is_active', 
      width: 90,
      render: (active) => active ? <Tag color="green">Aktif</Tag> : <Tag color="default">Nonaktif</Tag> 
    },
    {
      title: 'Aksi',
      key: 'aksi',
      width: 180,
      fixed: isMobile ? false : 'right',
      render: (_, record) => (
        <Space direction={isMobile ? "vertical" : "horizontal"} size={4} style={{ width: '100%' }}>
          <Button size="small" type="primary" icon={<SettingOutlined />} onClick={() => history.push(`/events/${record.id}`)} block={isMobile}>
            Kelola
          </Button>
          <Button size="small" icon={<EditOutlined />} onClick={() => openDrawer(record)} block={isMobile}>Edit</Button>
          <Popconfirm
            title="Hapus"
            description="Menghapus event juga menghapus seluruh sesinya. Apakah Anda yakin ingin menghapus event ini?"
            onConfirm={() => handleDelete(record.id)}
            okText="Ya"
            cancelText="Batal"
          >
            <Button size="small" danger icon={<DeleteOutlined />} block={isMobile}>Hapus</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>
          Tambah Event
        </Button>
      </div>
      <Table
        columns={columns}
        dataSource={events}
        loading={loading}
        rowKey="id"
        scroll={{ x: true }}
      />
      <Drawer
        title={editing ? 'Edit Event' : 'Tambah Event'}
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
            label="Nama"
            name="nama"
            rules={[
              { required: true, message: 'Nama wajib diisi' },
              { max: 150, message: 'Nama maksimal 150 karakter' },
            ]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label="Tanggal"
            name="tanggal"
            rules={[{ required: true, message: 'Tanggal wajib diisi' }]}
          >
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item
            label="Lokasi"
            name="lokasi"
            rules={[{ max: 150, message: 'Lokasi maksimal 150 karakter' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label="Aktif"
            name="is_active"
            valuePropName="checked"
          >
            <Switch />
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

export default EventList;
