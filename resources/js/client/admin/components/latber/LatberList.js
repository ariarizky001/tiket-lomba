import React, { useEffect, useState } from 'react';
import { Table, Button, Drawer, Form, DatePicker, Input, Popconfirm, message, Empty, Space, Grid } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SettingOutlined } from '@ant-design/icons';
import { useHistory } from 'react-router-dom';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah, formatAngka } from '../../../common/helpers/Utils';
import dayjs from 'dayjs';

const LatberList = () => {
  const history = useHistory();
  const screens = Grid.useBreakpoint();
  const isMobile = screens.xs;
  const [latbers, setLatbers] = useState([]);
  const [filteredLatbers, setFilteredLatbers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form] = Form.useForm();
  const [searchNama, setSearchNama] = useState('');
  const [dateRange, setDateRange] = useState(null);

  useEffect(() => {
    fetchLatbers();
  }, []);

  useEffect(() => {
    applyFilters();
  }, [latbers, searchNama, dateRange]);

  const fetchLatbers = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get('/latbers');
      setLatbers(res.data);
    } catch (error) {
      message.error('Gagal memuat latber');
    } finally {
      setLoading(false);
    }
  };

  const applyFilters = () => {
    let filtered = [...latbers];

    if (searchNama) {
      filtered = filtered.filter((l) =>
        l.nama.toLowerCase().includes(searchNama.toLowerCase())
      );
    }

    if (dateRange && dateRange[0] && dateRange[1]) {
      const start = dateRange[0].startOf('day');
      const end = dateRange[1].endOf('day');
      filtered = filtered.filter((l) => {
        const tanggal = dayjs(l.tanggal);
        return tanggal.isSameOrAfter(start) && tanggal.isSameOrBefore(end);
      });
    }

    setFilteredLatbers(filtered);
  };

  const openDrawer = (latber = null) => {
    if (latber) {
      setEditing(latber);
      form.setFieldsValue({
        ...latber,
        tanggal: dayjs(latber.tanggal),
      });
    } else {
      setEditing(null);
      form.resetFields();
      form.setFieldValue('tanggal', dayjs());
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
        await HTTP.put(`/latbers/${editing.id}`, data);
        message.success('Latber diperbarui');
      } else {
        await HTTP.post('/latbers', data);
        message.success('Latber ditambahkan');
      }

      setDrawerVisible(false);
      fetchLatbers();
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
        message.error('Gagal menyimpan latber');
      }
    }
  };

  const handleDelete = async (id) => {
    try {
      await HTTP.delete(`/latbers/${id}`);
      message.success('Latber dihapus');
      fetchLatbers();
    } catch (error) {
      const msg = error.response?.data?.message || 'Gagal menghapus latber';
      message.error(msg);
    }
  };

  const columns = [
    {
      title: 'Tanggal',
      dataIndex: 'tanggal',
      key: 'tanggal',
      width: 100,
      render: (text) => new Date(text).toLocaleDateString('id-ID'),
    },
    { 
      title: 'Nama', 
      dataIndex: 'nama', 
      key: 'nama',
      width: 150,
      fixed: isMobile ? false : 'left',
    },
    { 
      title: 'Lokasi', 
      dataIndex: 'lokasi', 
      key: 'lokasi',
      width: 120,
      className: isMobile ? 'ant-table-column-hidden-xs' : '',
    },
    {
      title: 'Sesi',
      key: 'sesi',
      align: 'center',
      width: 70,
      className: isMobile ? 'ant-table-column-hidden-xs' : '',
      render: (_, record) => record.ringkasan?.jumlah_sesi || 0,
    },
    {
      title: 'Tiket Terjual',
      key: 'terjual',
      align: 'center',
      width: 100,
      render: (_, record) => record.ringkasan?.total_terjual || 0,
    },
    {
      title: 'Pemasukan',
      key: 'pemasukan',
      width: 120,
      render: (_, record) => formatRupiah(record.ringkasan?.pemasukan || 0),
    },
    {
      title: 'Tiket Terpesan',
      key: 'terpesan',
      align: 'center',
      width: 100,
      className: isMobile ? 'ant-table-column-hidden-xs' : '',
      render: (_, record) => record.ringkasan?.total_terpesan || 0,
    },
    {
      title: 'Aksi',
      key: 'aksi',
      width: 180,
      fixed: isMobile ? false : 'right',
      render: (_, record) => (
        <Space direction={isMobile ? "vertical" : "horizontal"} size={4} style={{ width: '100%' }}>
          <Button size="small" type="primary" icon={<SettingOutlined />} onClick={() => history.push(`/latbers/${record.id}`)} block={isMobile}>
            Kelola
          </Button>
          <Button size="small" icon={<EditOutlined />} onClick={() => openDrawer(record)} block={isMobile}>Edit</Button>
          <Popconfirm
            title="Hapus"
            description="Apakah Anda yakin ingin menghapus latber ini?"
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
      <div style={{ marginBottom: 16, display: 'flex', gap: 8, flexWrap: 'wrap', flexDirection: isMobile ? 'column' : 'row' }}>
        <Input.Search
          placeholder="Cari nama latber"
          allowClear
          style={{ width: isMobile ? '100%' : 220 }}
          onChange={(e) => setSearchNama(e.target.value)}
        />
        <DatePicker.RangePicker
          onChange={(dates) => setDateRange(dates)}
          format="DD/MM/YYYY"
          style={{ width: isMobile ? '100%' : 'auto' }}
        />
        <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()} block={isMobile}>
          Tambah Latber
        </Button>
      </div>

      {filteredLatbers.length === 0 && !loading ? (
        <Empty description="Belum ada latber" />
      ) : (
        <Table
          columns={columns}
          dataSource={filteredLatbers}
          loading={loading}
          rowKey="id"
          scroll={{ x: true }}
        />
      )}

      <Drawer
        title={editing ? 'Edit Latber' : 'Tambah Latber'}
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
            <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
          </Form.Item>
          <Form.Item
            label="Lokasi"
            name="lokasi"
            rules={[{ max: 150, message: 'Lokasi maksimal 150 karakter' }]}
          >
            <Input />
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

export default LatberList;
