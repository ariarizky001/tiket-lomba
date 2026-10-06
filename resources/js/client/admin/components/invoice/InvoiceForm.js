import React, { useState, useEffect } from 'react';
import { Table, Button, Drawer, Form, Input, InputNumber, Select, Tag, message, Popconfirm, Space, Empty } from 'antd';
import { PlusOutlined, MinusCircleOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah } from '../../../common/helpers/Utils';

const { Option } = Select;

const InvoiceForm = ({ visible, invoice, event, sessionId, onSave, onCancel }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [sessions, setSessions] = useState([]);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    if (visible && event.id) {
      fetchSessions();
    }
  }, [visible, event.id]);

  useEffect(() => {
    if (invoice) {
      form.setFieldsValue({
        nama_peserta: invoice.nama_peserta,
        status: invoice.status,
        items: invoice.items.map((item) => ({
          event_session_id: item.event_session_id,
          qty: item.qty,
        })),
      });
      calculateTotal(invoice.items);
    } else {
      form.resetFields();
      form.setFieldValue('status', 'belum_lunas');
      setTotal(0);
    }
  }, [invoice, visible]);

  const fetchSessions = async () => {
    try {
      const res = await HTTP.get(`/events/${event.id}/sessions`);
      setSessions(res.data || []);
    } catch (error) {
      message.error('Gagal memuat sesi');
      setSessions([]);
    }
  };

  const calculateTotal = (items) => {
    let sum = 0;
    items.forEach((item) => {
      const session = sessions.find((s) => s.id === item.event_session_id);
      if (session) {
        sum += (item.qty || 0) * session.harga;
      }
    });
    setTotal(sum);
  };

  const handleFinish = async (values) => {
    setLoading(true);
    try {
      if (invoice) {
        const res = await HTTP.put(`/invoices/${invoice.id}`, values);
        message.success('Invoice diperbarui');
        onSave(res.data);
      } else {
        const res = await HTTP.post(`/events/${event.id}/invoices`, values);
        message.success('Invoice ditambahkan');
        onSave(res.data);
      }
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
        message.error(error.response?.data?.message || 'Gagal menyimpan invoice');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleValuesChange = (changedValues, allValues) => {
    if (changedValues.items) {
      calculateTotal(allValues.items || []);
    }
  };

  const getAvailableSessions = (currentIndex) => {
    const selectedSessionIds = (form.getFieldValue('items') || [])
      .map((item, index) => (index !== currentIndex ? item?.event_session_id : null))
      .filter(Boolean);

    return sessions.filter((session) => {
      const isSelected = selectedSessionIds.includes(session.id);
      const isCurrentInvoiceSession = invoice?.items?.some((item) => item.event_session_id === session.id);
      return !isSelected || isCurrentInvoiceSession;
    });
  };

  const getSessionOptions = (currentIndex) => {
    const availableSessions = getAvailableSessions(currentIndex);
    return availableSessions.map((session) => {
      const sisa = Number(session.sisa) || 0;
      const isCurrentInvoiceSession = invoice?.items?.some((item) => item.event_session_id === session.id);
      return {
        value: session.id,
        label: `${session.nama} — ${formatRupiah(session.harga)} — sisa ${sisa}`,
        disabled: sisa <= 0 && !isCurrentInvoiceSession,
      };
    });
  };

  return (
    <Drawer
      title={invoice ? 'Edit Invoice' : 'Tambah Peserta'}
      placement="right"
      width="100%"
      onClose={onCancel}
      open={visible}
    >
      <Form
        form={form}
        layout="vertical"
        onFinish={handleFinish}
        onValuesChange={handleValuesChange}
      >
        <Form.Item
          label="Nama Peserta"
          name="nama_peserta"
          rules={[{ required: true, message: 'Nama peserta wajib diisi' }]}
        >
          <Input />
        </Form.Item>

        <Form.List name="items">
          {(fields, { add, remove }) => (
            <>
              {sessions.length === 0 ? (
                <Empty description="Belum ada sesi di event ini" />
              ) : (
                <>
                  {fields.map((field, index) => (
                    <Space key={field.key} style={{ display: 'flex', marginBottom: 8 }} align="baseline">
                      <Form.Item
                        {...field}
                        label="Sesi"
                        name={[field.name, 'event_session_id']}
                        rules={[{ required: true, message: 'Pilih sesi' }]}
                      >
                        <Select style={{ width: 200 }} placeholder="Pilih sesi" options={getSessionOptions(index)} />
                      </Form.Item>
                      <Form.Item
                        {...field}
                        label="Qty"
                        name={[field.name, 'qty']}
                        rules={[{ required: true, message: 'Qty wajib diisi' }]}
                      >
                        <InputNumber min={1} max={100} />
                      </Form.Item>
                      <MinusCircleOutlined onClick={() => remove(field.name)} />
                    </Space>
                  ))}
                  <Form.Item>
                    <Button type="dashed" onClick={() => add()} block icon={<PlusOutlined />}>
                      Tambah Sesi
                    </Button>
                  </Form.Item>
                </>
              )}
            </>
          )}
        </Form.List>

        <Form.Item
          label="Status"
          name="status"
          rules={[{ required: true, message: 'Status wajib dipilih' }]}
        >
          <Select>
            <Option value="belum_lunas">Belum Lunas</Option>
            <Option value="lunas">Lunas</Option>
          </Select>
        </Form.Item>

        <Form.Item label="Total">
          <span style={{ fontSize: 18, fontWeight: 'bold' }}>{formatRupiah(total)}</span>
        </Form.Item>

        <Form.Item>
          <Button type="primary" htmlType="submit" loading={loading} block>
            Simpan & Buat Invoice
          </Button>
        </Form.Item>
      </Form>
    </Drawer>
  );
};

export default InvoiceForm;
