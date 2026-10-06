import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useHistory } from 'react-router-dom';
import { Layout, Menu, Button, Popconfirm, message } from 'antd';
import { LogoutOutlined } from '@ant-design/icons';
import { logout } from '../../redux/auth';

const { Header, Content } = Layout;

const AppLayout = ({ children, location }) => {
  const dispatch = useDispatch();
  const history = useHistory();
  const user = useSelector((state) => state.auth.user);

  const handleLogout = () => {
    dispatch(logout()).then(() => {
      message.success('Logout berhasil');
      history.push('/login');
    });
  };

  const menuItems = [
    { key: '/', label: 'Dashboard' },
    { key: '/events', label: 'Event' },
  ];

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Header style={{ background: '#fff', padding: '0 24px', boxShadow: '0 2px 8px rgba(0,0,0,0.1)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h2 style={{ margin: 0 }}>Tiket Lomba</h2>
        <Menu mode="horizontal" selectedKeys={[location.pathname]} items={menuItems} onClick={(e) => history.push(e.key)} style={{ flex: 1, marginLeft: 32 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <span>{user?.name}</span>
          <Popconfirm
            title="Keluar"
            description="Apakah Anda yakin ingin keluar?"
            onConfirm={handleLogout}
            okText="Ya"
            cancelText="Batal"
          >
            <Button type="primary" danger icon={<LogoutOutlined />}>
              Keluar
            </Button>
          </Popconfirm>
        </div>
      </Header>
      <Content style={{ padding: '24px' }}>
        {children}
      </Content>
    </Layout>
  );
};

export default AppLayout;
