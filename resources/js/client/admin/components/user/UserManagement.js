import React, { useState, useEffect } from 'react';
import { Table, Modal, Form, Input, Select, Button, message, Spin, Tag, Space, Typography } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { useSelector } from 'react-redux';

const { Title } = Typography;

const UserManagement = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [form] = Form.useForm();
  const user = useSelector((state) => state.auth.user);

  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    if (user?.role === 'superadmin') {
      loadData();
    }
  }, [user]);

  const loadData = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get('/users?search=' + encodeURIComponent(searchText));
      setUsers(res.data.data || []);
    } catch (err) {
      message.error(err.response?.data?.message || 'Gagal memuat data user');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (userData = null) => {
    if (userData) {
      setEditingUser(userData);
      form.setFieldsValue({
        name: userData.name,
        email: userData.email,
        role: userData.role,
      });
    } else {
      setEditingUser(null);
      form.resetFields();
    }
    setIsModalVisible(true);
  };

  const handleCloseModal = () => {
    setIsModalVisible(false);
    setEditingUser(null);
    form.resetFields();
  };

  const handleSubmit = async (values) => {
    setLoading(true);
    try {
      if (editingUser) {
        await HTTP.put(`/users/${editingUser.id}`, values);
        message.success('User berhasil diperbarui');
      } else {
        await HTTP.post('/users', values);
        message.success('User berhasil dibuat');
      }
      handleCloseModal();
      loadData();
    } catch (err) {
      message.error(err.response?.data?.message || 'Gagal menyimpan user');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (userData) => {
    Modal.confirm({
      title: 'Hapus User',
      content: `Apakah Anda yakin ingin menghapus user "${userData.name}" (${userData.email})?`,
      okText: 'Hapus',
      okType: 'danger',
      cancelText: 'Batal',
      onOk: async () => {
        setLoading(true);
        try {
          await HTTP.delete(`/users/${userData.id}`);
          message.success('User berhasil dihapus');
          loadData();
        } catch (err) {
          message.error(err.response?.data?.message || 'Gagal menghapus user');
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const columns = [
    {
      title: 'Nama',
      dataIndex: 'name',
      key: 'name',
      width: 200,
    },
    {
      title: 'Email',
      dataIndex: 'email',
      key: 'email',
      width: 250,
    },
    {
      title: 'Role',
      dataIndex: 'role',
      key: 'role',
      width: 150,
      render: (role) => {
        if (role === 'superadmin') return <Tag color="red">Superadmin</Tag>;
        if (role === 'latber') return <Tag color="blue">Latber</Tag>;
        return <Tag color="green">Admin</Tag>;
      },
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
        <Title level={4}>Manajemen User</Title>
        <div className="flex gap-2">
          <Input
            placeholder="Cari nama atau email"
            prefix={<SearchOutlined />}
            style={{ width: 250 }}
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            onPressEnter={loadData}
          />
          <Button icon={<PlusOutlined />} onClick={loadData}>
            Muat Ulang
          </Button>
          <Button icon={<PlusOutlined />} onClick={() => handleOpenModal()} type="primary">
            Tambah User
          </Button>
        </div>
      </div>

      {loading ? (
        <Spin />
      ) : (
        <>
          <Table
            columns={columns}
            dataSource={users}
            rowKey="id"
            pagination={{
              total: users.length,
              pageSize: 10,
              showSizeChanger: true,
              showTotal: (total) => `Total ${total} user`,
            }}
            bordered
            size="middle"
          />
          {users.length === 0 && (
            <div className="text-center py-8 text-gray-500">
              Belum ada user. Klik "Tambah User" untuk membuat user pertama.
            </div>
          )}
        </>
      )}

      <Modal
        title={editingUser ? 'Edit User' : 'Tambah User'}
        open={isModalVisible}
        onCancel={handleCloseModal}
        footer={[
          <Button key="cancel" onClick={handleCloseModal}>
            Batal
          </Button>,
          <Button key="submit" type="primary" loading={loading} onClick={() => form.validateFields().then(handleSubmit).catch(() => {})}>
            {editingUser ? 'Simpan Perubahan' : 'Tambah'}
          </Button>,
        ]}
      >
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
          <Form.Item
            label="Nama"
            name="name"
            rules={[{ required: true, message: 'Nama wajib diisi' }]}
          >
            <Input placeholder="Contoh: John Doe" />
          </Form.Item>

          <Form.Item
            label="Email"
            name="email"
            rules={[
              { required: true, message: 'Email wajib diisi' },
              { type: 'email', message: 'Format email tidak valid' },
            ]}
          >
            <Input placeholder="Contoh: user@example.com" />
          </Form.Item>

          {!editingUser && (
            <Form.Item
              label="Password"
              name="password"
              rules={[
                { required: true, message: 'Password wajib diisi' },
                { min: 8, message: 'Password minimal 8 karakter' },
              ]}
            >
              <Input type="password" placeholder="Minimal 8 karakter" />
            </Form.Item>
          )}

          <Form.Item
            label="Role"
            name="role"
            rules={[{ required: true, message: 'Role wajib dipilih' }]}
          >
            <Select placeholder="Pilih role">
              <Select.Option value="superadmin">
                <Tag color="red">Superadmin</Tag>
              </Select.Option>
              <Select.Option value="admin">
                <Tag color="green">Admin</Tag>
              </Select.Option>
              <Select.Option value="latber">
                <Tag color="blue">Latber</Tag>
              </Select.Option>
            </Select>
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default UserManagement;
