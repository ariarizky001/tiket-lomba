import React from 'react';
import { Table, Progress, Row, Col } from 'antd';
import { formatRupiah } from '../../../common/helpers/Utils';

const SessionReportTable = ({ sesi }) => {
  const columns = [
    {
      title: 'Sesi',
      dataIndex: 'nama',
      key: 'nama',
      width: 150,
      render: (text, record) => (
        <div>{record.nomor_urut ? `${record.nomor_urut}. ` : ''}{text}</div>
      ),
    },
    {
      title: 'Harga',
      dataIndex: 'harga',
      key: 'harga',
      render: (harga) => formatRupiah(harga),
      width: 120,
    },
    {
      title: 'Kuota',
      dataIndex: 'kuota',
      key: 'kuota',
      width: 80,
    },
    {
      title: 'Terjual',
      dataIndex: 'terjual',
      key: 'terjual',
      width: 80,
    },
    {
      title: 'Sisa',
      dataIndex: 'sisa',
      key: 'sisa',
      width: 80,
    },
    {
      title: 'Terisi',
      dataIndex: 'terjual',
      key: 'terisi',
      width: 100,
      render: (terjual, record) => (
        <Progress
          type="circle"
          percent={Math.round((terjual / record.kuota) * 100)}
          width={40}
          format={(percent) => `${percent}%`}
        />
      ),
    },
    {
      title: 'Pemasukan (Lunas)',
      dataIndex: 'pemasukan',
      key: 'pemasukan',
      render: (pemasukan) => formatRupiah(pemasukan),
      width: 150,
    },
    {
      title: 'Belum Lunas',
      dataIndex: 'belum_lunas',
      key: 'belum_lunas',
      render: (belumLunas) => (
        <span style={{ color: '#999', fontSize: 12 }}>{formatRupiah(belumLunas)}</span>
      ),
      width: 120,
    },
  ];

  const totalRow = sesi.length > 0 ? {
    kuota: sesi.reduce((sum, s) => sum + s.kuota, 0),
    terjual: sesi.reduce((sum, s) => sum + s.terjual, 0),
    sisa: sesi.reduce((sum, s) => sum + s.sisa, 0),
    pemasukan: sesi.reduce((sum, s) => sum + s.pemasukan, 0),
    belum_lunas: sesi.reduce((sum, s) => sum + s.belum_lunas, 0),
  } : null;

  return (
    <Table
      columns={columns}
      dataSource={sesi}
      rowKey="id"
      scroll={{ x: true }}
      pagination={false}
      summary={() => totalRow ? (
        <Table.Summary fixed>
          <Table.Summary.Row>
            <Table.Summary.Cell colSpan={2} style={{ fontWeight: 'bold' }}>Total</Table.Summary.Cell>
            <Table.Summary.Cell style={{ fontWeight: 'bold' }}>{totalRow.kuota}</Table.Summary.Cell>
            <Table.Summary.Cell style={{ fontWeight: 'bold' }}>{totalRow.terjual}</Table.Summary.Cell>
            <Table.Summary.Cell style={{ fontWeight: 'bold' }}>{totalRow.sisa}</Table.Summary.Cell>
            <Table.Summary.Cell></Table.Summary.Cell>
            <Table.Summary.Cell style={{ fontWeight: 'bold' }}>{formatRupiah(totalRow.pemasukan)}</Table.Summary.Cell>
            <Table.Summary.Cell style={{ fontWeight: 'bold' }}>
              <span style={{ color: '#999', fontSize: 12 }}>{formatRupiah(totalRow.belum_lunas)}</span>
            </Table.Summary.Cell>
          </Table.Summary.Row>
        </Table.Summary>
      ) : null}
    />
  );
};

export default SessionReportTable;
