import React, { useState, useEffect, useRef, useMemo } from 'react';
import { Spin, Empty, message, Button, Typography } from 'antd';
import { DownloadOutlined, ReloadOutlined, FullscreenOutlined, FullscreenExitOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';
import SessionTable from './SessionTable';

const { Title, Text } = Typography;

const RekapFullscreen = ({ eventId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showControls, setShowControls] = useState(true);
  const [mouseMoveTimer, setMouseMoveTimer] = useState(null);
  const containerRef = useRef(null);
  const refreshIntervalRef = useRef(null);

  useEffect(() => {
    loadData();
    setupFullscreenListener();
    setupMouseMoveListener();
    return () => {
      stopAutoRefresh();
      document.removeEventListener('fullscreenchange', handleFullscreenChange);
      document.removeEventListener('mousemove', handleMouseMove);
    };
  }, [eventId]);

  const setupFullscreenListener = () => {
    document.addEventListener('fullscreenchange', handleFullscreenChange);
  };

  const handleFullscreenChange = () => {
    const fullscreen = document.fullscreenElement === containerRef.current;
    setIsFullscreen(fullscreen);
    if (fullscreen) {
      setShowControls(false);
      setMouseMoveTimer(setTimeout(() => setShowControls(true), 3000));
    } else {
      setShowControls(true);
    }
  };

  const handleMouseMove = () => {
    if (isFullscreen) {
      setShowControls(true);
      if (mouseMoveTimer) clearTimeout(mouseMoveTimer);
      setMouseMoveTimer(setTimeout(() => setShowControls(true), 3000));
    }
  };

  const setupMouseMoveListener = () => {
    document.addEventListener('mousemove', handleMouseMove);
  };

  const loadData = async () => {
    try {
      const response = await HTTP.get(`/events/${eventId}/rekap-peserta`);
      setData(response.data);
    } catch (err) {
      console.error('Error loading rekap peserta:', err);
      // Don't show error if we have existing data
      if (!data) {
        setError(err.response?.data?.message || err.message || 'Gagal memuat data rekap peserta');
        message.error('Gagal memuat data rekap peserta');
      }
    }
  };

  const startAutoRefresh = () => {
    refreshIntervalRef.current = setInterval(() => {
      if (!document.hidden && data) {
        loadData();
      }
    }, 15000);
  };

  const stopAutoRefresh = () => {
    if (refreshIntervalRef.current) {
      clearInterval(refreshIntervalRef.current);
      refreshIntervalRef.current = null;
    }
  };

  useEffect(() => {
    const handleVisibilityChange = () => {
      if (document.hidden) {
        stopAutoRefresh();
      } else {
        startAutoRefresh();
      }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      stopAutoRefresh();
    };
  }, []);

  const toggleFullscreen = async () => {
    if (!isFullscreen) {
      try {
        await containerRef.current.requestFullscreen();
      } catch (err) {
        console.error('Fullscreen error:', err);
        message.error('Gagal masuk mode layar penuh');
      }
    } else {
      try {
        await document.exitFullscreen();
      } catch (err) {
        console.error('Exit fullscreen error:', err);
      }
    }
  };

  const handleReload = () => {
    loadData();
  };

  if (loading && !data) {
    return (
      <div className="flex items-center justify-center h-screen bg-white">
        <Spin size="large" />
      </div>
    );
  }

  if (error && !data) {
    return (
      <div className="flex items-center justify-center h-screen bg-white">
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description={error}
        />
      </div>
    );
  }

  if (!data || !data.sesi || data.sesi.length === 0) {
    return (
      <div className="flex items-center justify-center h-screen bg-white">
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description="Belum ada sesi untuk event ini"
        />
      </div>
    );
  }

  const { event, sesi } = data;

  const boardStyle = useMemo(() => {
    const maxKuota = Math.max(...sesi.map(s => s.kuota), 24);
    const sessionCount = sesi.length;
    const rowsPerPage = Math.ceil(sessionCount / 5);
    const maxRowsInPage = maxKuota * rowsPerPage;

    // Calculate available height (100vh - margins - header)
    const headerHeight = 80; // approximate header height
    const margin = 16; // 8px * 2
    const availableHeight = `calc(100vh - ${headerHeight + margin}px)`;

    const rowHeight = Math.max(18, Math.floor((window.innerHeight - headerHeight - margin) / maxRowsInPage));
    const fontSize = Math.max(10, rowHeight * 0.5);

    return {
      container: {
        height: availableHeight,
        overflow: maxRowsInPage * 18 > window.innerHeight - headerHeight - margin ? 'auto' : 'hidden',
      },
      grid: {
        gridTemplateColumns: 'repeat(5, minmax(0, 1fr))',
      },
      rowHeight,
      fontSize,
    };
  }, [sesi]);

  if (!data || !data.sesi || data.sesi.length === 0) {
    return (
      <div className="flex items-center justify-center h-screen bg-white">
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description="Belum ada sesi untuk event ini"
        />
      </div>
    );
  }

  return (
    <div ref={containerRef} className="h-screen w-screen bg-white flex flex-col overflow-hidden">
      {/* Controls overlay */}
      <div
        className={`fixed top-4 right-4 z-50 flex items-center gap-2 px-3 py-2 bg-white/80 backdrop-blur-sm rounded-lg shadow-lg transition-opacity duration-300 ${
          showControls ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
      >
        <Button
          size="small"
          icon={isFullscreen ? <FullscreenExitOutlined /> : <FullscreenOutlined />}
          onClick={toggleFullscreen}
          title={isFullscreen ? 'Keluar Layar Penuh (Esc)' : 'Layar Penuh'}
        />
        <Button size="small" icon={<ReloadOutlined />} onClick={handleReload} title="Muat Ulang" />
      </div>

      {/* Header */}
      <header className="flex-shrink-0 bg-gray-50 border-b border-gray-200 px-4 py-3">
        <div className="max-w-full mx-auto flex items-center justify-between">
          <div>
            <Title level={3} style={{ margin: 0 }} className="text-gray-900">
              {data.event?.nama || 'Event'}
            </Title>
            <Text className="text-gray-500 text-sm">
              {data.event?.tanggal ? new Date(data.event.tanggal).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '-'}
              {data.event?.lokasi && ` - ${data.event.lokasi}`}
            </Text>
          </div>
          <div className="text-xs text-gray-400">
            Auto-refresh 15s | {data.sesi.length} sesi
          </div>
        </div>
      </header>

      {/* Board */}
      <main
        ref={containerRef}
        className="flex-1 overflow-auto p-4"
        style={boardStyle.container}
      >
        <div
          className="grid gap-3"
          style={boardStyle.grid}
        >
          {data.sesi.map((s) => (
            <SessionTable key={s.id} sesi={s} />
          ))}
        </div>
      </main>
    </div>
  );
};

export default RekapFullscreen;