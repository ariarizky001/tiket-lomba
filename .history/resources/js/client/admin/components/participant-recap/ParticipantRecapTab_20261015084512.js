// import React, { useState, useEffect } from 'react';
// import { Spin, Empty, message, Button, Typography } from 'antd';
// import { DownloadOutlined, PictureOutlined } from '@ant-design/icons';
// import HTTP from '../../../../common/helpers/HTTP';
// import GantanganGrid from './GantanganGrid';

// const { Title, Text } = Typography;

// const ParticipantRecapTab = ({ eventId }) => {
//   const [data, setData] = useState(null);
//   const [loading, setLoading] = useState(true);
//   const [error, setError] = useState(null);

//   useEffect(() => {
//     loadData();
//   }, [eventId]);

//   const loadData = async () => {
//     setLoading(true);
//     setError(null);
//     try {
//       const response = await HTTP.get(`/events/${eventId}/participant-recap`);
//       setData(response.data);
//     } catch (err) {
//       console.error('Error loading participant recap:', err);
//       setError(err.response?.data?.message || err.message || 'Gagal memuat data peserta');
//       message.error('Gagal memuat data peserta');
//     } finally {
//       setLoading(false);
//     }
//   };

//   const handleExportPDF = () => {
//     window.print();
//   };

//   const handleExportJPG = () => {
//     message.info('Gunakan "Print to PDF" lalu convert ke JPG');
//   };

//   if (loading) {
//     return <Spin />;
//   }

//   if (error) {
//     return (
//       <Empty
//         image={Empty.PRESENTED_IMAGE_SIMPLE}
//         description={error}
//       />
//     );
//   }

//   if (!data || !data.sesi || data.sesi.length === 0) {
//     return (
//       <Empty
//         image={Empty.PRESENTED_IMAGE_SIMPLE}
//         description="Belum ada data peserta"
//       />
//     );
//   }

//   const { event, sesi } = data;

//   return (
//     <div className="h-screen w-screen bg-[#2d2d2d] flex flex-col p-2 overflow-hidden font-sans select-none">
//       {/* Header Event */}
//       <div className="flex-shrink-0 text-center mb-2">
//         <Title level={1} className="text-yellow-500 font-black text-lg md:text-xl tracking-wide uppercase drop-shadow-md leading-none mb-1" style={{ margin: 0 }}>
//           {event?.nama || 'Event'}
//         </Title>
//         <Text className="text-gray-200 text-[10px] font-bold tracking-wider leading-none">
//           {event?.tanggal ? new Date(event.tanggal).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '-'}
//           {event?.lokasi && ` - ${event.lokasi}`}
//         </Text>
//       </div>

//       {/* Toolbar */}
//       <div className="flex-shrink-0 mb-2 flex justify-end no-print">
//         <Button type="primary" icon={<DownloadOutlined />} onClick={handleExportPDF} style={{ marginRight: 8 }}>
//           Export PDF
//         </Button>
//         <Button icon={<PictureOutlined />} onClick={handleExportJPG}>
//           Export JPG
//         </Button>
//       </div>

//       {/* Main Content - Horizontal Scrollable Grid */}
//       <main className="flex-1 min-h-0 grid grid-cols-5 gap-1.5 w-full overflow-x-auto overflow-y-hidden p-1">
//         {sesi.map((sesi) => (
//           <GantanganGrid key={sesi.id} sesi={sesi} />
//         ))}
//       </main>
//     </div>
//   );
// };

// export default ParticipantRecapTab;
