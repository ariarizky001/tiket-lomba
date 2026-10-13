import React, { useState, useEffect } from 'react';
import { Spin, message } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import BookkeepingSummary from './BookkeepingSummary';
import ExpenseTable from './ExpenseTable';
import ExpenseDrawer from './ExpenseDrawer';

const ExpenseTab = ({ eventId }) => {
  const [expenses, setExpenses] = useState([]);
  const [bookkeeping, setBookkeeping] = useState(null);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [editingExpense, setEditingExpense] = useState(null);

  useEffect(() => {
    loadData();
    loadCategories();
  }, [eventId]);

  const loadCategories = async () => {
    try {
      const response = await HTTP.get('/expense-categories');
      setCategories(response.data);
    } catch (err) {
      message.error('Gagal memuat kategori');
    }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const [expensesRes, bookkeepingRes] = await Promise.all([
        HTTP.get(`/events/${eventId}/expenses`),
        HTTP.get(`/events/${eventId}/bookkeeping`),
      ]);
      setExpenses(expensesRes.data);
      setBookkeeping(bookkeepingRes.data);
    } catch (err) {
      message.error('Gagal memuat data pengeluaran');
    } finally {
      setLoading(false);
    }
  };

  const handleEditExpense = (expense) => {
    setEditingExpense(expense);
    setDrawerVisible(true);
  };

  const handleCloseDrawer = () => {
    setDrawerVisible(false);
    setEditingExpense(null);
  };

  if (loading) {
    return <Spin />;
  }

  return (
    <div>
      <BookkeepingSummary bookkeeping={bookkeeping} onRefresh={loadData} />
      <ExpenseTable
        eventId={eventId}
        expenses={expenses}
        onEdit={handleEditExpense}
        onDelete={() => loadData()}
        onRefresh={loadData}
        categories={categories}
      />
      <ExpenseDrawer
        visible={drawerVisible}
        expense={editingExpense}
        eventId={eventId}
        categories={categories}
        onClose={handleCloseDrawer}
        onRefresh={loadData}
      />
    </div>
  );
};

export default ExpenseTab;
