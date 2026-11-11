import React, { useState, useEffect } from 'react';
import CountGrid from './CountGrid';
import HTTP from '../../../../common/helpers/HTTP';
import { message } from 'antd';

const SoldTab = ({ latber, onSessionsUpdate }) => {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchSessions();
  }, [latber.id]);

  const fetchSessions = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get(`/latbers/${latber.id}/sessions`);
      setSessions(res.data);
    } catch (error) {
      message.error('Gagal memuat data sesi');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (changedPayload) => {
    setLoading(true);
    try {
      const res = await HTTP.put(`/latbers/${latber.id}/terjual`, {
        sesi: changedPayload,
      });
      message.success('Tersimpan');
      setSessions(res.data);
      if (onSessionsUpdate) {
        onSessionsUpdate(res.data);
      }
    } catch (error) {
      const msg = error.response?.data?.message || 'Gagal menyimpan data';
      message.error(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <CountGrid
      sessions={sessions}
      onSave={handleSave}
      mode="sold"
      loading={loading}
    />
  );
};

export default SoldTab;
