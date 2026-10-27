import React, { useState, useEffect } from 'react';
import { Tag, Modal, Button, message, Spin } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';

const InvoiceView = ({ invoice, event, onClose }) => {
  const [loading, setLoading] = useState(true);
  const [teks, setTeks] = useState('');

  useEffect(() => {
    if (invoice && invoice.id) {
      fetchInvoiceText();
    }
  }, [invoice]);

  const fetchInvoiceText = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/invoices/${invoice.id}/teks`);
      setTeks(res.data.teks);
    } catch (err) {
      message.error('Gagal memuat teks invoice');
    } finally {
      setLoading(false);
    }
  };

  const getStatusTag = () => {
    if (invoice.status === 'lunas') {
      return <Tag color="success">LUNAS</Tag>;
    }
    return <Tag color="error">BELUM LUNAS</Tag>;
  };

  const handleCopy = () => {
    if (!teks) return;
    navigator.clipboard.writeText(teks).then(() => {
      message.success('Tersalin');
    }).catch(() => {
      message.error('Gagal menyalin teks');
    });
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <>
      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #printable-invoice-content, #printable-invoice-content * {
            visibility: visible;
          }
          #printable-invoice-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 16px;
            font-size: 14px;
            border: none !important;
          }
          .ant-modal-close, .ant-modal-footer, .invoice-action-buttons {
            display: none !important;
          }
        }
      `}</style>

      <Modal
        title={
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <span>Invoice {invoice.kode}</span>
            {getStatusTag()}
          </div>
        }
        open={true}
        onCancel={onClose}
        footer={null}
        width={600}
        bodyStyle={{ padding: 24 }}
      >
        {loading ? (
          <Spin style={{ display: 'block', margin: '40px auto' }} />
        ) : (
          <div>
            <div id="printable-invoice-content">
              <pre style={{
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                fontSize: 13,
                fontFamily: 'monospace',
                background: '#fff',
                border: '1px solid #d9d9d9',
                borderRadius: 6,
                padding: 16,
                minHeight: 200,
                margin: 0
              }}>
                {teks}
              </pre>
            </div>

            <div className="invoice-action-buttons" style={{ display: 'flex', gap: 12, marginTop: 24 }}>
              <Button style={{ flex: 1 }} onClick={handlePrint}>
                Cetak
              </Button>
              <Button style={{ flex: 1 }} onClick={handleCopy} type="primary">
                Salin Teks
              </Button>
              <Button style={{ flex: 1 }} onClick={onClose}>
                Tutup
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </>
  );
};

export default InvoiceView;
