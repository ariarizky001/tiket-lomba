import React, { useState, useEffect, useRef } from 'react';
import { useDispatch } from 'react-redux';
import { useHistory } from 'react-router-dom';
import { Card, Form, Input, Button, message } from 'antd';
import { login } from '../../redux/auth';

const Login = () => {
  const dispatch = useDispatch();
  const history = useHistory();
  const [loading, setLoading] = useState(false);
  const [form] = Form.useForm();
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const onFinish = async (values) => {
    setLoading(true);
    try {
      await dispatch(login(values.email, values.password));
      if (!mountedRef.current) return;
      message.success('Login berhasil');
      history.push('/');
    } catch (error) {
      if (!mountedRef.current) return;
      message.error(error.response?.data?.message || 'Login gagal');
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  };

  return (
    <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
      <Card style={{ width: 400 }}>
        <h2 style={{ textAlign: 'center', marginBottom: 24 }}>Tiket Lomba</h2>
        <Form
          form={form}
          layout="vertical"
          onFinish={onFinish}
        >
          <Form.Item
            label="Email"
            name="email"
            rules={[
              { required: true, message: 'Email wajib diisi' },
              { type: 'email', message: 'Format email tidak valid' },
            ]}
          >
            <Input autocomplete="email" placeholder="Masukkan email" />
          </Form.Item>
          <Form.Item
            label="Password"
            name="password"
            rules={[{ required: true, message: 'Password wajib diisi' }]}
          >
            <Input.Password autocomplete="current-password" placeholder="Masukkan password" />
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" loading={loading} block>
              Masuk
            </Button>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
};

export default Login;
