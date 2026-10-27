import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { BrowserRouter as Router, Switch, Route, Redirect } from 'react-router-dom';
import { Spin } from 'antd';
import { initAuth } from '../redux/auth';
import Login from '../components/auth/Login';
import AppLayout from '../components/layout/AppLayout';
import Dashboard from '../components/dashboard/Dashboard';
import EventList from '../components/event/EventList';
import EventRoom from '../components/event/EventRoom';
import RekapDashboard from '../components/rekap/RekapDashboard';

const App = () => {
  const dispatch = useDispatch();
  const { user, ready } = useSelector((state) => state.auth);

  useEffect(() => {
    dispatch(initAuth());
  }, [dispatch]);

  if (!ready) {
    return <Spin />;
  }

  return (
    <Router basename="/admin">
      <Switch>
        <Route path="/login">
          {user ? <Redirect to="/" /> : <Login />}
        </Route>
        <Route path="/events/:id/rekap-dashboard">
          <RekapDashboard />
        </Route>
        <Route path="/">
          {!user ? (
            <Redirect to="/login" />
          ) : (
            <AppLayout location={window.location}>
              <Switch>
                <Route exact path="/">
                  <Dashboard />
                </Route>
                <Route exact path="/events">
                  <EventList />
                </Route>
                <Route path="/events/:id">
                  <EventRoom />
                </Route>
              </Switch>
            </AppLayout>
          )}
        </Route>
      </Switch>
    </Router>
  );
};

export default App;
