import React, { useState } from 'react';
import { Modal, Form, Input, Button } from 'antd';

const InvoiceTemplateModal = ({ invoiceJudul, invoiceCatatan, onSave, onCancel }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);
      onSave(values);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title="Pengaturan Invoice"
      open={true}
      onCancel={onCancel}
      footer={null}
      width={500}
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          invoice_judul: invoiceJudul || '',
          invoice_catatan: invoiceCatatan || '',
        }}
      >
        <Form.Item
          label="Judul Invoice"
          name="invoice_judul"
          rules={[{ max: 100, message: 'Judul maksimal 100 karakter' }]}
        >
          <Input placeholder="Default: INVOICE" />
        </Form.Item>
        <Form.Item
          label="Catatan"
          name="invoice_catatan"
          rules={[{ max: 1000, message: 'Catatan maksimal 1000 karakter' }]}
        >
          <Input.TextArea rows={4} placeholder="Opsional" />
        </Form.Item>
        <Form.Item style={{ textAlign: 'right' }}>
          <Button style={{ marginRight: 8 }} onClick={onCancel}>
            Batal
          </Button>
          <Button type="primary" onClick={handleSubmit} loading={loading}>
            Simpan
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default InvoiceTemplateModal;
