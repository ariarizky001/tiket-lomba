import React, { useEffect, useState } from 'react';
import { Row, Col, Card, Empty, Spin } from 'antd';
import { useHistory } from 'react-router-dom';
import HTTP from '../../../../common/helpers/HTTP';

const Dashboard = () => {
  const history = useHistory();
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchEvents();
  }, []);

  const fetchEvents = async () => {
    setLoading(true);
    try {
      const res = await HTTP.get('/events');
      const activeEvents = res.data.filter((e) => e.is_active);
      setEvents(activeEvents);
    } catch (error) {
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <Spin />;
  }

  if (events.length === 0) {
    return <Empty description="Belum ada event aktif" />;
  }

  return (
    <Row gutter={[16, 16]}>
      {events.map((event) => (
        <Col key={event.id} xs={24} sm={12} md={8}>
          <Card onClick={() => history.push(`/events/${event.id}`)} style={{ cursor: 'pointer' }}>
            <h3>{event.nama}</h3>
            <p>Tanggal: {new Date(event.tanggal).toLocaleDateString('id-ID')}</p>
            {event.lokasi && <p>Lokasi: {event.lokasi}</p>}
          </Card>
        </Col>
      ))}
    </Row>
  );
};

export default Dashboard;
