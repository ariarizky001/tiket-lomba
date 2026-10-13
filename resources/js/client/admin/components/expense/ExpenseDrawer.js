import React, { useState, useEffect } from 'react';
import { Drawer, Form, Input, DatePicker, Select, InputNumber, Button, message } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import dayjs from 'dayjs';

const ExpenseDrawer = ({ visible, expense, eventId, categories, onClose, onRefresh }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (expense) {
      form.setFieldsValue({
        tanggal: dayjs(expense.tanggal),
        kategori: expense.kategori,
        keterangan: expense.keterangan,
        penerima: expense.penerima,
        nominal: expense.nominal,
      });
    } else {
      form.setFieldsValue({
        tanggal: dayjs(),
        kategori: undefined,
        keterangan: '',
        penerima: '',
        nominal: undefined,
      });
    }
  }, [expense, visible, form]);

  const onFinish = async (values) => {
    setLoading(true);
    try {
      const payload = {
        tanggal: values.tanggal.format('YYYY-MM-DD'),
        kategori: values.kategori,
        keterangan: values.keterangan,
        penerima: values.penerima || null,
        nominal: values.nominal,
      };

      if (expense) {
        await HTTP.put(`/expenses/${expense.id}`, payload);
        message.success('Pengeluaran diperbarui');
      } else {
        await HTTP.post(`/events/${eventId}/expenses`, payload);
        message.success('Pengeluaran ditambahkan');
      }
      onRefresh();
      onClose();
    } catch (err) {
      const errors = err.response?.data?.errors || {};
      if (Object.keys(errors).length > 0) {
        form.setFields(
          Object.entries(errors).map(([name, msgs]) => ({
            name,
            errors: msgs,
          }))
        );
      } else {
        message.error('Gagal menyimpan pengeluaran');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Drawer
      title={expense ? 'Edit Pengeluaran' : 'Tambah Pengeluaran'}
      placement="right"
      onClose={onClose}
      open={visible}
      width={400}
    >
      <Form form={form} layout="vertical" onFinish={onFinish}>
        <Form.Item
          label="Tanggal"
          name="tanggal"
          rules={[{ required: true, message: 'Tanggal wajib diisi' }]}
        >
          <DatePicker format="DD/MM/YYYY" style={{ width: '100%' }} />
        </Form.Item>

        <Form.Item
          label="Kategori"
          name="kategori"
          rules={[{ required: true, message: 'Kategori wajib dipilih' }]}
        >
          <Select
            placeholder="Pilih kategori"
            options={categories.map(cat => ({ label: cat, value: cat }))}
          />
        </Form.Item>

        <Form.Item
          label="Keterangan"
          name="keterangan"
          rules={[
            { required: true, message: 'Keterangan wajib diisi' },
            { max: 255, message: 'Maksimal 255 karakter' },
          ]}
        >
          <Input placeholder="Contoh: Gaji juri sesi A" />
        </Form.Item>

        <Form.Item
          label="Penerima"
          name="penerima"
          rules={[{ max: 150, message: 'Maksimal 150 karakter' }]}
        >
          <Input placeholder="Opsional" />
        </Form.Item>

        <Form.Item
          label="Nominal"
          name="nominal"
          rules={[
            { required: true, message: 'Nominal wajib diisi' },
            { type: 'number', min: 1, message: 'Minimal Rp 1' },
          ]}
        >
          <InputNumber
            formatter={(value) => `Rp ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}
            parser={(value) => parseInt(value.replace(/Rp\s?|(\.)/g, ''))}
            style={{ width: '100%' }}
          />
        </Form.Item>

        <Form.Item>
          <Button type="primary" htmlType="submit" block loading={loading}>
            Simpan
          </Button>
        </Form.Item>
      </Form>
    </Drawer>
  );
};

export default ExpenseDrawer;
