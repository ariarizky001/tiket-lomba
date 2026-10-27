import React from 'react';
import { Tag, Space, Typography } from 'antd';
import { InfoCircleOutlined } from '@ant-design/icons';

const { Text } = Typography;

const PlaceholderHelp = ({ activeSectionKey, onInsert }) => {
  const placeholders = {
    umum: [
      '{nama_event}', '{nama_event_besar}', '{tanggal_event}', '{tanggal_event_besar}',
      '{lokasi_event}', '{nama_pemesan}', '{nomor_urut}', '{kode_invoice}',
      '{tanggal_invoice}', '{total}', '{total_angka}', '{status}', '{status_ikon}',
    ],
    baris: [
      '{no}', '{nama_sesi}', '{harga}', '{harga_angka}', '{qty}', '{subtotal}', '{subtotal_angka}',
    ],
  };

  const isBarisActive = activeSectionKey === 'kelas_pesanan';

  const handleClick = (placeholder) => {
    if (onInsert) {
      onInsert(placeholder);
    }
  };

  return (
    <div>
      <Space direction="vertical" size={8} style={{ width: '100%' }}>
        <div>
          <Text strong>Placeholder Umum</Text>
          <Text type="secondary" style={{ fontSize: 12, marginLeft: 8 }}>
            <InfoCircleOutlined /> Dapat digunakan di semua bagian
          </Text>
        </div>
        <Space wrap>
          {placeholders.umum.map(ph => (
            <Tag
              key={ph}
              color="blue"
              onClick={() => handleClick(ph)}
              style={{ cursor: 'pointer' }}
            >
              {ph}
            </Tag>
          ))}
        </Space>

        <div>
          <Text strong>Placeholder Khusus Baris</Text>
          <Text type="secondary" style={{ fontSize: 12, marginLeft: 8 }}>
            <InfoCircleOutlined /> Hanya tersedia untuk Format Baris
          </Text>
        </div>
        <Space wrap>
          {placeholders.baris.map(ph => (
            <Tag
              key={ph}
              color={isBarisActive ? "green" : "default"}
              onClick={isBarisActive ? () => handleClick(ph) : undefined}
              style={{ cursor: isBarisActive ? 'pointer' : 'not-allowed', opacity: isBarisActive ? 1 : 0.5 }}
            >
              {ph}
            </Tag>
          ))}
        </Space>
      </Space>
    </div>
  );
};

export default PlaceholderHelp;
