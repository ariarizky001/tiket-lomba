import React, { useState, useEffect, useMemo } from 'react';
import { Table, Tabs, Switch, message, Spin, Tag, Typography, Tooltip } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { useSelector } from 'react-redux';

const { Title, Text } = Typography;

const PermissionManagement = () => {
  const [permissions, setPermissions] = useState({});
  const [loading, setLoading] = useState(false);
  const [activeRole, setActiveRole] = useState('admin');
  const [rolePermissions, setRolePermissions] = useState([]);
  const [assignedIds, setAssignedIds] = useState([]);
  const user = useSelector((state) => state.auth.user);

  useEffect(() => {
    if (user?.role === 'superadmin') {
      loadPermissions();
      loadRolePermissions(activeRole);
    }
  }, [user, activeRole]);

  const loadPermissions = async () => {
    try {
      const res = await HTTP.get('/permissions');
      setPermissions(res.data);
    } catch (err) {
      message.error('Gagal memuat permissions');
    }
  };

  const loadRolePermissions = async (role) => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/permissions/role/${role}`);
      setRolePermissions(res.data.permissions || []);
      setAssignedIds(res.data.assigned_permission_ids || []);
    } catch (err) {
      message.error('Gagal memuat role permissions');
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = async (permissionId, checked) => {
    const newAssigned = checked
      ? [...assignedIds, permissionId]
      : assignedIds.filter((id) => id !== permissionId);

    setAssignedIds(newAssigned);

    try {
      await HTTP.put(`/permissions/role/${activeRole}`, {
        permission_ids: newAssigned,
      });
      message.success('Permission diperbarui');
    } catch (err) {
      message.error('Gagal memperbarui permission');
      setAssignedIds(assignedIds);
    }
  };

  const categories = useMemo(() => {
    const flatPermissions = Object.values(permissions).flat();
    return [...new Set(flatPermissions.map((p) => p.category))];
  }, [permissions]);

  const tabItems = [
    {
      key: 'admin',
      label: (
        <span>
          <UserOutlined />
          Admin
        </span>
      ),
    },
    {
      key: 'latber',
      label: (
        <span>
          <UserOutlined />
          Latber
        </span>
      ),
    },
    {
      key: 'superadmin',
      label: (
        <span>
          <LockOutlined />
          Superadmin
        </span>
      ),
    },
  ];

  if (categories.length === 0) {
    return <Spin tip="Memuat permissions..." />;
  }

  return (
    <div className="p-4">
      <div className="flex items-center justify-between mb-4">
        <Title level={4}>Manajemen Permission</Title>
        <Text type="secondary" style={{ fontSize: 13 }}>
          Atur hak akses per role. Perubahan otomatis tersimpan.
        </Text>
      </div>

      <Tabs
        type="card"
        items={tabItems}
        activeKey={activeRole}
        onChange={(key) => {
          setActiveRole(key);
          loadRolePermissions(key);
        }}
      />

      <div className="mt-4">
        {categories.map((category) => {
          const categoryPermissions = permissions[category] || [];
          return (
            <div key={category} className="mb-6">
              <div className="flex items-center justify-between mb-2">
                <Title level={5} style={{ textTransform: 'capitalize' }}>
                  {category.replace('_', ' ')}
                </Title>
                <Tag color="blue" style={{ fontSize: 11 }}>
                  {categoryPermissions.length} permissions
                </Tag>
              </div>
              <Table
                dataSource={categoryPermissions}
                columns={[
                  {
                    title: 'Permission',
                    dataIndex: 'name',
                    key: 'name',
                    width: 200,
                    render: (name, record) => (
                      <div>
                        <Text code style={{ fontSize: 12, background: '#f0f0f0', padding: '2px 4px' }}>
                          {name}
                        </Text>
                        <Tooltip title={record.description || ''}>
                          <Text type="secondary" style={{ marginLeft: 8, fontSize: 12 }}>
                            {record.display_name}
                          </Text>
                        </Tooltip>
                      </div>
                    ),
                  },
                  {
                    title: 'Status',
                    dataIndex: 'id',
                    key: 'status',
                    width: 100,
                    render: (_, record) => (
                      <Switch
                        checked={assignedIds.includes(record.id)}
                        onChange={(checked) => handleToggle(record.id, checked)}
                        disabled={activeRole === 'superadmin'}
                        loading={loading}
                        size="small"
                      />
                    ),
                  },
                  {
                    title: 'Deskripsi',
                    dataIndex: 'description',
                    key: 'description',
                    render: (desc) => desc || <Text type="secondary" style={{ fontSize: 12 }}>-</Text>,
                  },
                ]}
                pagination={false}
                bordered
                size="small"
                rowClassName={() => 'text-center'}
              />
            </div>
          );
        })}
      </div>

      {activeRole === 'superadmin' && (
        <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
          <div className="flex items-center gap-2">
            <LockOutlined style={{ color: '#faad14' }} />
            <Text strong>Superadmin memiliki akses penuh ke semua permission.</Text>
          </div>
          <Text type="secondary" style={{ fontSize: 12, marginTop: 4 }}>
            Permission Superadmin tidak bisa diubah dari sini. Superadmin selalu memiliki akses ke semua fitur.
          </Text>
        </div>
      )}
    </div>
  );
};

export default PermissionManagement;