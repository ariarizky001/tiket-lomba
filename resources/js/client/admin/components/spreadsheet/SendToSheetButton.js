import React, { useState } from 'react';
import { Button, message, Popconfirm } from 'antd';
import { SendOutlined, LoadingOutlined, CheckCircleOutlined, ExclamationCircleOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';

const SendToSheetButton = ({ eventId, sessionId, sessionName, onSuccess }) => {
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState(null); // null, 'success', 'error'

  const handleSend = async () => {
    setLoading(true);
    setStatus(null);
    try {
      const response = await HTTP.post(`/events/${eventId}/spreadsheet/send-rekap`, {
        session_id: sessionId,
      });
      setStatus('success');
      message.success(`Berhasil kirim ${response.data.rows_sent} data ke ${response.data.range}`);
      if (onSuccess) onSuccess(response.data);
    } catch (err) {
      setStatus('error');
      message.error(err.response?.data?.message || 'Gagal mengirim ke spreadsheet');
    } finally {
      setLoading(false);
      setTimeout(() => setStatus(null), 3000);
    }
  };

  const getButtonProps = () => {
    if (loading) {
      return {
        icon: <LoadingOutlined />,
        children: 'Mengirim...',
        loading: true,
      };
    }
    if (status === 'success') {
      return {
        icon: <CheckCircleOutlined />,
        children: 'Terkirim',
        type: 'success',
      };
    }
    if (status === 'error') {
      return {
        icon: <ExclamationCircleOutlined />,
        children: 'Gagal',
        danger: true,
      };
    }
    return {
      icon: <SendOutlined />,
      children: 'Kirim ke Sheet',
    };
  };

  const props = getButtonProps();

  return (
    <Popconfirm
      title="Kirim data peserta sesi ini ke Google Sheets?"
      okText="Ya, Kirim"
      cancelText="Batal"
      onConfirm={handleSend}
      disabled={loading}
    >
      <Button
        type="primary"
        size="small"
        {...props}
        disabled={loading}
      />
    </Popconfirm>
  );
};

export default SendToSheetButton;