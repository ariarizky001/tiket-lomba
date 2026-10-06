import React, { useState, useEffect } from 'react';
import { Table, Button, Input, Select, Tag, Popconfirm, message, Empty, Space } from 'antd';
import { EyeOutlined, EditOutlined, CheckOutlined, StopOutlined, DeleteOutlined, SettingOutlined, UserAddOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import { formatRupiah, sessionName } from '../../../common/helpers/Utils';
import InvoiceForm from './InvoiceForm';
import InvoiceView from './InvoiceView';
import InvoiceTemplateModal from './InvoiceTemplateModal';

const { Search } = Input;
const { Option } = Select;

const InvoiceTab = ({ event }) => {
  const [invoices, setInvoices] = useState([]);
  const [loading, setLoading] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [viewVisible, setViewVisible] = useState(false);
  const [templateVisible, setTemplateVisible] = useState(false);
  const [selectedInvoice, setSelectedInvoice] = useState(null);
  const [statusFilter, setStatusFilter] = useState('semua');
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    fetchInvoices();
  }, [event.id, statusFilter, searchQuery]);

  const fetchInvoices = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (statusFilter !== 'semua') {
        params.append('status', statusFilter);
      }
      if (searchQuery) {
        params.append('q', searchQuery);
      }
      const res = await HTTP.get(`/events/${event.id}/invoices?${params.toString()}`);
      setInvoices(res.data);
    } catch (error) {
      message.error('Gagal memuat invoice');
    } finally {
      setLoading(false);
    }
  };

  const handleAdd = () => {
    setSelectedInvoice(null);
    setFormVisible(true);
  };

  const handleEdit = (invoice) => {
    setSelectedInvoice(invoice);
    setFormVisible(true);
  };

  const handleView = (invoice) => {
    setSelectedInvoice(invoice);
    setViewVisible(true);
  };

  const handleStatusToggle = async (invoice) => {
    try {
      const newStatus = invoice.status === 'lunas' ? 'belum_lunas' : 'lunas';
      await HTTP.patch(`/invoices/${invoice.id}/status`, { status: newStatus });
      message.success(`Invoice ditandai ${newStatus === 'lunas' ? 'lunas' : 'belum lunas'}`);
      fetchInvoices();
    } catch (error) {
      message.error('Gagal mengubah status');
    }
  };

  const handleDelete = async (invoice) => {
    try {
      await HTTP.delete(`/invoices/${invoice.id}`);
      message.success('Invoice dihapus');
      fetchInvoices();
    } catch (error) {
      message.error('Gagal menghapus invoice');
    }
  };

  const handleSaveForm = (invoice) => {
    setFormVisible(false);
    fetchInvoices();
    if (!selectedInvoice) {
      setSelectedInvoice(invoice);
      setViewVisible(true);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const columns = [
    {
      title: 'Kode',
      dataIndex: 'kode',
      key: 'kode',
      width: 120,
    },
    {
      title: 'Nama Peserta',
      dataIndex: 'nama_peserta',
      key: 'nama_peserta',
      render: (text, record) => (
        <span style={{ color: record.status === 'belum_lunas' ? '#ff4d4f' : '#000' }}>
          {text}
        </span>
      ),
    },
    {
      title: 'Sesi Dibeli',
      dataIndex: 'items',
      key: 'items',
      render: (items) => (
        <Space wrap>
          {items.map((item) => (
            <Tag key={item.id}>
              {sessionName(item)} ×{item.qty}
            </Tag>
          ))}
        </Space>
      ),
    },
    {
      title: 'Total',
      dataIndex: 'total',
      key: 'total',
      width: 120,
      render: (total) => formatRupiah(total),
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status) =>
        status === 'lunas' ? (
          <Tag color="success">Lunas</Tag>
        ) : (
          <Tag color="error">Belum Lunas</Tag>
        ),
    },
    {
      title: 'Aksi',
      key: 'aksi',
      width: 220,
      render: (_, record) => (
        <Space>
          <Button size="small" icon={<EyeOutlined />} onClick={() => handleView(record)}>
            Lihat
          </Button>
          <Button size="small" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            Edit
          </Button>
          <Popconfirm
            title={record.status === 'lunas' ? 'Tandai belum lunas?' : 'Tandai lunas?'}
            onConfirm={() => handleStatusToggle(record)}
            okText="Ya"
            cancelText="Batal"
          >
            <Button size="small" icon={record.status === 'lunas' ? <StopOutlined /> : <CheckOutlined />}>
              {record.status === 'lunas' ? 'Batal' : 'Lunas'}
            </Button>
          </Popconfirm>
          <Popconfirm
            title="Hapus invoice?"
            onConfirm={() => handleDelete(record)}
            okText="Ya"
            cancelText="Batal"
          >
            <Button size="small" danger icon={<DeleteOutlined />}>
              Hapus
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Space style={{ marginBottom: 16 }} wrap>
        <Search
          placeholder="Cari nama/kode..."
          style={{ width: 200 }}
          onSearch={setSearchQuery}
          allowClear
        />
        <Select value={statusFilter} onChange={setStatusFilter} style={{ width: 150 }}>
          <Option value="semua">Semua</Option>
          <Option value="lunas">Lunas</Option>
          <Option value="belum_lunas">Belum Lunas</Option>
        </Select>
        <Button type="primary" icon={<UserAddOutlined />} onClick={handleAdd}>
          Tambah Peserta
        </Button>
        <Button icon={<SettingOutlined />} onClick={() => setTemplateVisible(true)}>
          Pengaturan Invoice
        </Button>
      </Space>

      {invoices.length === 0 && !loading ? (
        <Empty description="Belum ada peserta" />
      ) : (
        <Table
          columns={columns}
          dataSource={invoices}
          loading={loading}
          rowKey="id"
          scroll={{ x: true }}
        />
      )}

<InvoiceForm
        visible={formVisible}
        invoice={selectedInvoice}
        event={event}
        onSave={handleSaveForm}
        onCancel={() => setFormVisible(false)}
      />

      {viewVisible && selectedInvoice && (
        <InvoiceView
          invoice={selectedInvoice}
          event={event}
          onClose={() => setViewVisible(false)}
          onPrint={handlePrint}
        />
      )}

      {templateVisible && (
        <InvoiceTemplateModal
          invoiceJudul={event.invoice_judul}
          invoiceCatatan={event.invoice_catatan}
          onSave={(values) => {
            HTTP.put(`/events/${event.id}/invoice-template`, values).then((res) => {
              message.success('Pengaturan invoice disimpan');
              setTemplateVisible(false);
            });
          }}
          onCancel={() => setTemplateVisible(false)}
        />
      )}
    </div>
  );
};

export default InvoiceTab;
