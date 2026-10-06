import React from 'react';
import { Tag, Modal, Button, message } from 'antd';
import dayjs from 'dayjs';
import { sessionName } from '../../../common/helpers/Utils';

const InvoiceView = ({ invoice, event, onClose, onPrint, onCopy }) => {
  const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  });

  const getStatusTag = () => {
    if (invoice.status === 'lunas') {
      return <Tag color="success">Lunas</Tag>;
    }
    return <Tag color="error">Belum Lunas</Tag>;
  };

  const formatTitle = () => {
    return invoice.invoice_judul || 'INVOICE';
  };

  const formatTitleEvent = () => {
    return event?.nama || '';
  };

  const formatInvoiceDate = () => {
    return dayjs(invoice.created_at).format('DD/MM/YYYY HH:mm');
  };

  const formatPaidDate = () => {
    return invoice.paid_at ? dayjs(invoice.paid_at).format('DD/MM/YYYY HH:mm') : '-';
  };

  const formatNamaPeserta = () => {
    return invoice.nama_peserta || '';
  };

  const handlePrint = () => {
    onPrint();
  };

  const handleCopy = () => {
    const lines = [];
    lines.push(`*${formatTitle()} ${invoice.kode}*`);
    lines.push(`Event: ${formatTitleEvent()}`);
    lines.push(`Nama: ${formatNamaPeserta()}`);
    lines.push('');
    invoice.items.forEach((item) => {
      lines.push(`${sessionName(item)} x${item.qty} = ${rupiah.format(item.subtotal)}`);
    });
    lines.push(`*Total: ${rupiah.format(invoice.total)}*`);
    lines.push(`Status: ${invoice.status === 'lunas' ? 'LUNAS' : 'BELUM LUNAS'}`);
    lines.push('');
    if (invoice.invoice_catatan) {
      lines.push(invoice.invoice_catatan);
    }
    const text = lines.join('\n');
    navigator.clipboard.writeText(text).then(() => {
      message.success('Tersalin');
    });
  };

  return (
    <Modal
      title={`${formatTitle()} ${invoice.kode}`}
      open={true}
      onCancel={onClose}
      footer={null}
      width={600}
      bodyStyle={{ padding: 24 }}
    >
      <div style={{ marginBottom: 24 }}>
        <p style={{ margin: '8px 0' }}>Event: <strong>{formatTitleEvent()}</strong></p>
        <p style={{ margin: '8px 0' }}>Tanggal: {formatInvoiceDate()}</p>
        <p style={{ margin: '8px 0' }}>Nama: <strong>{formatNamaPeserta()}</strong></p>
      </div>

      <table style={{ width: '100%', marginBottom: 16, borderCollapse: 'collapse' }}>
        <thead>
          <tr>
            <th style={{ border: '1px solid #ddd', padding: 8, textAlign: 'left' }}>Sesi</th>
            <th style={{ border: '1px solid #ddd', padding: 8, textAlign: 'center' }}>Qty</th>
            <th style={{ border: '1px solid #ddd', padding: 8, textAlign: 'right' }}>Harga</th>
            <th style={{ border: '1px solid #ddd', padding: 8, textAlign: 'right' }}>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {invoice.items.map((item) => (
            <tr key={item.id}>
              <td style={{ border: '1px solid #ddd', padding: 8 }}>{sessionName(item)}</td>
              <td style={{ border: '1px solid #ddd', padding: 8, textAlign: 'center' }}>{item.qty}</td>
              <td style={{ border: '1px solid #ddd', padding: 8, textAlign: 'right' }}>{rupiah.format(item.harga)}</td>
              <td style={{ border: '1px solid #ddd', padding: 8, textAlign: 'right' }}>{rupiah.format(item.subtotal)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div style={{ textAlign: 'right', marginBottom: 16 }}>
        <p style={{ margin: 0, fontSize: 16, fontWeight: 'bold' }}>
          Total: {rupiah.format(invoice.total)}
        </p>
        <p style={{ margin: '8px 0 0 0' }}>{getStatusTag()}</p>
        {invoice.status === 'lunas' && <p style={{ margin: '4px 0 0 0', color: '#52c41a' }}>Bayar: {formatPaidDate()}</p>}
      </div>

      {invoice.invoice_catatan && (
        <div style={{ marginTop: 16, padding: 12, backgroundColor: '#f5f5f5', borderRadius: 4 }}>
          <p style={{ margin: 0, fontSize: 14, color: '#666' }}>{invoice.invoice_catatan}</p>
        </div>
      )}

      <div style={{ display: 'flex', gap: 12, marginTop: 24 }}>
        <Button style={{ flex: 1 }} onClick={handlePrint}>Cetak</Button>
        <Button style={{ flex: 1 }} onClick={handleCopy} type="primary">Salin WhatsApp</Button>
        <Button style={{ flex: 1 }} onClick={onClose}>Tutup</Button>
      </div>
    </Modal>
  );
};

export default InvoiceView;
