import React, { useState, useEffect } from 'react';
import { Table, Button, Popconfirm, message, Tag, Empty, Select, DatePicker, Input, Row, Col } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import dayjs from 'dayjs';
import 'dayjs/locale/id';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import isBetween from 'dayjs/plugin/isBetween';

dayjs.extend(localizedFormat);
dayjs.extend(isBetween);
dayjs.locale('id');

const ExpenseTable = ({ eventId, expenses, onEdit, onDelete, onRefresh, categories }) => {
  const [filtered, setFiltered] = useState(expenses);
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [dateRange, setDateRange] = useState([null, null]);
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    let mounted = true;

    let result = expenses;

    if (selectedCategory !== 'all') {
      result = result.filter(e => e.kategori === selectedCategory);
    }

    if (dateRange[0] && dateRange[1]) {
      const start = dayjs(dateRange[0]);
      const end = dayjs(dateRange[1]);
      result = result.filter(e => {
        const d = dayjs(e.tanggal);
        return d.isBetween(start, end, null, '[]');
      });
    }

    if (searchText) {
      result = result.filter(e => 
        (e.keterangan && e.keterangan.toLowerCase().includes(searchText.toLowerCase())) ||
        (e.penerima && e.penerima.toLowerCase().includes(searchText.toLowerCase()))
      );
    }

    if (mounted) {
      setFiltered(result);
    }

    return () => { mounted = false; };
  }, [expenses, selectedCategory, dateRange, searchText]);

  const handleDelete = async (expenseId) => {
    try {
      await HTTP.delete(`/expenses/${expenseId}`);
      message.success('Pengeluaran dihapus');
      onRefresh();
    } catch (err) {
      message.error('Gagal menghapus pengeluaran');
    }
  };

  const totalNominal = filtered.reduce((sum, e) => sum + e.nominal, 0);

  const columns = [
    {
      title: 'Tanggal',
      dataIndex: 'tanggal',
      key: 'tanggal',
      render: (text) => dayjs(text).format('DD/MM/YYYY'),
      width: 100,
    },
    {
      title: 'Kategori',
      dataIndex: 'kategori',
      key: 'kategori',
      render: (text) => <Tag>{text}</Tag>,
      width: 120,
    },
    {
      title: 'Keterangan',
      dataIndex: 'keterangan',
      key: 'keterangan',
    },
    {
      title: 'Penerima',
      dataIndex: 'penerima',
      key: 'penerima',
      render: (text) => text || '-',
      width: 150,
    },
    {
      title: 'Nominal',
      dataIndex: 'nominal',
      key: 'nominal',
      render: (text) => `Rp ${text.toLocaleString('id-ID')}`,
      align: 'right',
      width: 130,
    },
    {
      title: 'Aksi',
      key: 'aksi',
      render: (_, record) => (
        <div>
          <Button type="text" size="small" icon={<EditOutlined />} onClick={() => onEdit(record)} />
          <Popconfirm
            title="Hapus pengeluaran ini?"
            onConfirm={() => handleDelete(record.id)}
            okText="Ya"
            cancelText="Tidak"
          >
            <Button type="text" size="small" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </div>
      ),
      width: 100,
    },
  ];

  return (
    <div>
      <Row gutter={[8, 8]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} md={6}>
          <Button type="primary" icon={<PlusOutlined />} block onClick={() => onEdit(null)}>
            Tambah Pengeluaran
          </Button>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Select
            value={selectedCategory}
            onChange={setSelectedCategory}
            style={{ width: '100%' }}
            options={[
              { label: 'Semua Kategori', value: 'all' },
              ...categories.map(cat => ({ label: cat, value: cat })),
            ]}
          />
        </Col>
        <Col xs={24} sm={12} md={6}>
          <DatePicker.RangePicker
            value={dateRange}
            onChange={(dates) => setDateRange(dates)}
            onClick={(e) => e.stopPropagation()}
            style={{ width: '100%', zIndex: 9999 }}
            format="DD/MM/YYYY"
            getPopupContainer={() => document.body}
            allowClear={false}
            showTime={false}
          />
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Input.Search
            placeholder="Cari keterangan/penerima"
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
          />
        </Col>
      </Row>

      {filtered.length === 0 ? (
        <Empty description="Belum ada pengeluaran" />
      ) : (
        <Table
          columns={columns}
          dataSource={filtered.map(e => ({ ...e, key: e.id }))}
          scroll={{ x: true }}
          pagination={false}
          summary={() => (
            <Table.Summary.Row>
              <Table.Summary.Cell colSpan={4} align="right">
                <strong>Total</strong>
              </Table.Summary.Cell>
              <Table.Summary.Cell align="right">
                <strong>Rp {totalNominal.toLocaleString('id-ID')}</strong>
              </Table.Summary.Cell>
              <Table.Summary.Cell />
            </Table.Summary.Row>
          )}
        />
      )}
    </div>
  );
};

export default ExpenseTable;
