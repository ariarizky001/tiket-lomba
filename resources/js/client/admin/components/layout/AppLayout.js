import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useHistory } from 'react-router-dom';
import { Layout, Menu, Button, Popconfirm, message, Tag, Drawer, Avatar, Dropdown, Space, Grid } from 'antd';
import { LogoutOutlined, MenuOutlined, UserOutlined, SettingOutlined } from '@ant-design/icons';
import { logout } from '../../redux/auth';

const { Header, Content } = Layout;

const AppLayout = ({ children, location }) => {
  const dispatch = useDispatch();
  const history = useHistory();
  const user = useSelector((state) => state.auth.user);
  const permissions = useSelector((state) => state.auth.permissions);
  const screens = Grid.useBreakpoint();
  const isMobile = screens.xs || screens.sm;
  const [drawerVisible, setDrawerVisible] = React.useState(false);

  const handleLogout = () => {
    dispatch(logout()).then(() => {
      message.success('Logout berhasil');
      history.push('/login');
    });
  };

  const menuItems = [
    { key: '/', label: 'Dashboard', icon: <SettingOutlined /> },
  ];

  if (user?.role === 'superadmin' || permissions.includes('view_events')) {
    menuItems.push({ key: '/events', label: 'Event', icon: <SettingOutlined /> });
  }

  if (user?.role === 'superadmin' || permissions.includes('view_latbers')) {
    menuItems.push({ key: '/latbers', label: 'Latber', icon: <SettingOutlined /> });
  }

  if (user?.role === 'superadmin') {
    menuItems.push({ key: '/users', label: 'Kelola User', icon: <UserOutlined /> });
    menuItems.push({ key: '/permissions', label: 'Kelola Permission', icon: <SettingOutlined /> });
  }

  const userMenuItems = [
    {
      label: (
        <div>
          <strong>{user?.name}</strong>
        </div>
      ),
      key: 'user-info',
      disabled: true,
    },
    { type: 'divider' },
    {
      label: 'Keluar',
      key: 'logout',
      icon: <LogoutOutlined />,
      onClick: handleLogout,
      danger: true,
    },
  ];

  const getRoleTag = (role) => {
    if (role === 'superadmin') return <Tag color="red">Superadmin</Tag>;
    if (role === 'latber') return <Tag color="blue">Latber</Tag>;
    return <Tag color="green">Admin</Tag>;
  };

  const handleMenuClick = (e) => {
    history.push(e.key);
    if (isMobile) setDrawerVisible(false);
  };

  if (isMobile) {
    return (
      <Layout style={{ minHeight: '100vh' }}>
        <Header style={{ background: '#fff', padding: '0 16px', boxShadow: '0 2px 8px rgba(0,0,0,0.1)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', height: 56 }}>
          <span style={{ fontSize: 18, fontWeight: 600, color: '#1890ff' }}>Tiket Lomba</span>
          <Button type="text" icon={<MenuOutlined />} onClick={() => setDrawerVisible(true)} size="large" aria-label="Buka Menu" />
        </Header>
        <Drawer
          placement="left"
          closable={false}
          onClose={() => setDrawerVisible(false)}
          open={drawerVisible}
          width={280}
          style={{ zIndex: 1000 }}
        >
          <div style={{ padding: 24, borderBottom: '1px solid #f0f0f0' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <Avatar size="large" icon={<UserOutlined />} style={{ backgroundColor: '#1890ff' }} />
              <div>
                <div style={{ fontWeight: 600, fontSize: 16 }}>{user?.name}</div>
                <div style={{ fontSize: 12, color: '#8c8c8c', marginTop: 4 }}>{getRoleTag(user?.role)}</div>
              </div>
            </div>
          </div>
          <Menu
            mode="inline"
            selectedKeys={[location.pathname]}
            items={menuItems}
            onClick={handleMenuClick}
            style={{ borderRight: 'none' }}
          />
          <div style={{ padding: 16, borderTop: '1px solid #f0f0f0' }}>
            <Popconfirm
              title="Keluar"
              description="Apakah Anda yakin ingin keluar?"
              onConfirm={handleLogout}
              okText="Ya"
              cancelText="Batal"
            >
              <Button type="primary" danger icon={<LogoutOutlined />} block>
                Keluar
              </Button>
            </Popconfirm>
          </div>
        </Drawer>
        <Content style={{ padding: '16px' }}>
          {children}
        </Content>
      </Layout>
    );
  }

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Header style={{ background: '#fff', padding: '0 24px', boxShadow: '0 2px 8px rgba(0,0,0,0.1)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', height: 64 }}>
        <h2 style={{ margin: 0, fontSize: 20, color: '#1890ff' }}>Tiket Lomba</h2>
        <Menu mode="horizontal" selectedKeys={[location.pathname]} items={menuItems} onClick={handleMenuClick} style={{ flex: 1, marginLeft: 32 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <Dropdown menu={{ items: userMenuItems }} trigger={['click']} placement="bottomRight">
            <Space style={{ alignItems: 'center', gap: 8 }}>
              <Avatar size={32} icon={<UserOutlined />} style={{ backgroundColor: '#1890ff' }} />
              <span style={{ fontWeight: 500 }}>{user?.name}</span>
              {getRoleTag(user?.role)}
            </Space>
          </Dropdown>
        </div>
      </Header>
      <Content style={{ padding: '24px' }}>
        {children}
      </Content>
    </Layout>
  );
};

export default AppLayout;
