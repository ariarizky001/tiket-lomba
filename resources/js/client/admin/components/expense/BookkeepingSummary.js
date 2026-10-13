import React, { useState, useEffect } from 'react';
import { Row, Col, Statistic, Tag, Empty, Button, Drawer, Form, Input, DatePicker, Select, InputNumber, Table, Popconfirm, message } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import dayjs from 'dayjs';

const BookkeepingSummary = ({ bookkeeping, onRefresh }) => {
  if (!bookkeeping) return null;

  const { pemasukan, belum_lunas, hadiah, pengeluaran, saldo, per_kategori } = bookkeeping;

  return (
    <div style={{ marginBottom: 24 }}>
      <Row gutter={[16, 16]}>
        <Col xs={12} md={6}>
          <Statistic title="Pemasukan (Lunas)" value={pemasukan} prefix="Rp " suffix=" " formatter={(value) => value.toLocaleString('id-ID')} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic title="Hadiah" value={hadiah} prefix="Rp " suffix=" " formatter={(value) => value.toLocaleString('id-ID')} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic title="Total Pengeluaran" value={pengeluaran} prefix="Rp " suffix=" " formatter={(value) => value.toLocaleString('id-ID')} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic 
            title="Saldo Akhir" 
            value={saldo} 
            prefix="Rp " 
            suffix=" " 
            formatter={(value) => value.toLocaleString('id-ID')}
            valueStyle={{ color: saldo >= 0 ? '#52c41a' : '#ff4d4f' }}
          />
        </Col>
      </Row>
      
      {belum_lunas > 0 && (
        <div style={{ marginTop: 12, fontSize: 12, color: '#999' }}>
          Belum lunas Rp {belum_lunas.toLocaleString('id-ID')} (belum dihitung sebagai pemasukan)
        </div>
      )}
      
      {per_kategori && per_kategori.length > 0 && (
        <div style={{ marginTop: 12 }}>
          {per_kategori.map(item => (
            <Tag key={item.kategori} style={{ marginRight: 8, marginBottom: 4 }}>
              {item.kategori} · Rp {item.total.toLocaleString('id-ID')}
            </Tag>
          ))}
        </div>
      )}
    </div>
  );
};

export default BookkeepingSummary;
