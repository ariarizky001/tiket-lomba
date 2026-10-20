import React, { useState, useEffect } from 'react';
import { Form, Input, Button, message, Spin, Card, Typography, Modal, Descriptions } from 'antd';
import { LinkOutlined, ApiOutlined, DeleteOutlined } from '@ant-design/icons';
import HTTP from '../../../../common/helpers/HTTP';

const { Title } = Typography;

const SpreadsheetConnection = ({ eventId }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [spreadsheet, setSpreadsheet] = useState(null);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);
  const [isModalVisible, setIsModalVisible] = useState(false);

  useEffect(() => {
    loadSpreadsheet();
  }, [eventId]);

  const loadSpreadsheet = async () => {
    setLoading(true);
    try {
      const response = await HTTP.get(`/events/${eventId}/spreadsheet`);
      setSpreadsheet(response.data.spreadsheet);
      if (response.data.spreadsheet) {
        form.setFieldsValue({
          spreadsheet_url: response.data.spreadsheet.spreadsheet_url,
          sheet_name: response.data.spreadsheet.sheet_name,
        });
      }
    } catch (err) {
      // Not connected yet, no error
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (values) => {
    setLoading(true);
    try {
      if (spreadsheet) {
        // Update existing
        await HTTP.put(`/events/${eventId}/spreadsheet`, values);
        message.success('Koneksi spreadsheet diperbarui');
      } else {
        // Create new
        await HTTP.post(`/events/${eventId}/spreadsheet`, values);
        message.success('Koneksi spreadsheet berhasil dibuat');
      }
      await loadSpreadsheet();
    } catch (err) {
      message.error(err.response?.data?.message || 'Gagal menyimpan koneksi spreadsheet');
    } finally {
      setLoading(false);
    }
  };

  const handleTestConnection = async () => {
    setTesting(true);
    setTestResult(null);
    try {
      const response = await HTTP.post(`/events/${eventId}/spreadsheet/test`);
      setTestResult({
        success: true,
        title: response.data.title,
        sheets: response.data.sheets,
      });
      message.success('Koneksi berhasil!');
      setIsModalVisible(true);
    } catch (err) {
      setTestResult({
        success: false,
        error: err.response?.data?.error || err.message,
      });
      message.error('Koneksi gagal: ' + (err.response?.data?.error || err.message));
    } finally {
      setTesting(false);
    }
  };

  const handleDelete = async () => {
    Modal.confirm({
      title: 'Hapus Koneksi Spreadsheet',
      content: 'Apakah Anda yakin ingin menghapus koneksi spreadsheet untuk event ini?',
      okText: 'Hapus',
      okType: 'danger',
      cancelText: 'Batal',
      onOk: async () => {
        setLoading(true);
        try {
          await HTTP.delete(`/events/${eventId}/spreadsheet`);
          message.success('Koneksi spreadsheet berhasil dihapus');
          setSpreadsheet(null);
          form.resetFields();
        } catch (err) {
          message.error(err.response?.data?.message || 'Gagal menghapus koneksi');
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const showTestResultModal = () => {
    setIsModalVisible(true);
  };

  const handleModalClose = () => {
    setIsModalVisible(false);
  };

  if (loading && !spreadsheet) {
    return <Spin />;
  }

  return (
    <div className="p-4">
      <Card
        title={<Title level={4}>Koneksi Google Sheets</Title>}
        extra={
          spreadsheet && (
            <Button
              icon={<DeleteOutlined />}
              danger
              onClick={handleDelete}
              loading={loading}
            >
              Hapus Koneksi
            </Button>
          )
        }
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
          disabled={loading}
        >
          <Form.Item
            label="URL Google Sheets"
            name="spreadsheet_url"
            rules={[
              { required: true, message: 'URL spreadsheet diperlukan' },
              { type: 'url', message: 'Format URL tidak valid' },
            ]}
            help="Contoh: https://docs.google.com/spreadsheets/d/1abc123/edit"
          >
            <Input
              placeholder="https://docs.google.com/spreadsheets/d/..."
              prefix={<LinkOutlined />}
            />
          </Form.Item>

          <Form.Item
            label="Nama Sheet"
            name="sheet_name"
            help="Nama sheet dalam spreadsheet (default: 'Daftar Peserta')"
          >
            <Input placeholder="Daftar Peserta" />
          </Form.Item>

          <div className="flex gap-2">
            <Button
              type="primary"
              htmlType="submit"
              loading={loading}
            >
              {spreadsheet ? 'Perbarui Koneksi' : 'Simpan Koneksi'}
            </Button>

            {spreadsheet && (
              <Button
                icon={<ApiOutlined />}
                onClick={handleTestConnection}
                loading={testing}
              >
                Test Koneksi
              </Button>
            )}
          </div>
        </Form>

        {spreadsheet && (
          <div className="mt-6">
            <Descriptions title="Status Koneksi" bordered column={1} size="small">
              <Descriptions.Item label="Spreadsheet ID">
                {spreadsheet.spreadsheet_id}
              </Descriptions.Item>
              <Descriptions.Item label="URL">
                <a href={spreadsheet.spreadsheet_url} target="_blank" rel="noopener noreferrer">
                  {spreadsheet.spreadsheet_url}
                </a>
              </Descriptions.Item>
              <Descriptions.Item label="Nama Sheet">
                {spreadsheet.sheet_name}
              </Descriptions.Item>
              <Descriptions.Item label="Terakhir Disinkron">
                {spreadsheet.last_synced_at 
                  ? new Date(spreadsheet.last_synced_at).toLocaleString('id-ID')
                  : 'Belum pernah disinkron'}
              </Descriptions.Item>
            </Descriptions>
          </div>
        )}

        <Modal
          title="Hasil Test Koneksi"
          open={isModalVisible}
          onCancel={handleModalClose}
          footer={[
            <Button key="close" onClick={handleModalClose}>
              Tutup
            </Button>,
          ]}
        >
          {testResult?.success ? (
            <div>
              <p><strong>Status:</strong> <span style={{ color: 'green' }}>Berhasil Terhubung</span></p>
              <p><strong>Judul Spreadsheet:</strong> {testResult.title}</p>
              <p><strong>Sheets yang tersedia:</strong></p>
              <ul>
                {testResult.sheets.map((sheet, index) => (
                  <li key={index}>{sheet.name} (ID: {sheet.id})</li>
                ))}
              </ul>
            </div>
          ) : testResult?.error ? (
            <div>
              <p><strong>Status:</strong> <span style={{ color: 'red' }}>Gagal Terhubung</span></p>
              <p><strong>Error:</strong> {testResult.error}</p>
              <p style={{ marginTop: '10px' }}>
                Pastikan:
                <ol>
                  <li>Spreadsheet telah di-share ke service account</li>
                  <li>URL spreadsheet valid</li>
                  <li>Credentials file ada di server</li>
                </ol>
              </p>
            </div>
          ) : (
            <Spin />
          )}
        </Modal>
      </Card>
    </div>
  );
};

export default SpreadsheetConnection;
