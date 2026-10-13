import React from 'react';
import { Row, Col, Statistic } from 'antd';
import { formatRupiah } from '../../../common/helpers/Utils';

const ReportSummary = ({ ringkasan }) => {
  return (
    <div>
      <h3>Ringkasan</h3>
      <Row gutter={[16, 16]}>
        <Col xs={12} md={6}>
          <Statistic
            title="Total Pemasukan (Lunas)"
            value={ringkasan.pemasukan}
            formatter={(value) => formatRupiah(value)}
          />
        </Col>
        <Col xs={12} md={6}>
          <Statistic
            title="Total Hadiah"
            value={ringkasan.total_hadiah}
            formatter={(value) => formatRupiah(value)}
          />
        </Col>
        <Col xs={12} md={6}>
          <Statistic
            title="Laba Bersih"
            value={ringkasan.laba}
            formatter={(value) => formatRupiah(value)}
            valueStyle={{ color: ringkasan.laba >= 0 ? '#52c41a' : '#ff4d4f' }}
          />
        </Col>
        <Col xs={12} md={6}>
          <Statistic
            title="Belum Lunas"
            value={ringkasan.belum_lunas}
            formatter={(value) => formatRupiah(value)}
          />
        </Col>
      </Row>
      <div style={{ marginTop: 24, fontSize: 12, color: '#999' }}>
        <p>Invoice: {ringkasan.jumlah_invoice} total, {ringkasan.invoice_lunas} lunas, {ringkasan.invoice_belum_lunas} belum lunas</p>
      </div>
    </div>
  );
};

export default ReportSummary;
