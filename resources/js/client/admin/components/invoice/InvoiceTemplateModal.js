import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Modal, Form, Input, Button, Collapse, Switch, Tag, Popconfirm, message, Spin, Row, Col, Alert } from 'antd';
import HTTP from '../../../../common/helpers/HTTP';
import PlaceholderHelp from './PlaceholderHelp';

const { Panel } = Collapse;
const { TextArea } = Input;

const SECTION_TITLES = {
  salam_pembuka: '1. Salam Pembuka',
  nama_event: '2. Nama Event',
  pemesan: '3. Nama Pemesan & No Urut',
  kelas_pesanan: '4. Kelas yang Dipesan',
  total_pembayaran: '5. Total Pembayaran',
  ketentuan_pembayaran: '6. Ketentuan Pembayaran',
  penutup: '7. Penutup',
  iklan: '8. Advertisement',
};

const SECTION_KEYS = [
  'salam_pembuka',
  'nama_event',
  'pemesan',
  'kelas_pesanan',
  'total_pembayaran',
  'ketentuan_pembayaran',
  'penutup',
  'iklan',
];

const InvoiceTemplateModal = ({ event, onCancel }) => {
  const [loading, setLoading] = useState(false);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [template, setTemplate] = useState(null);
  const [serverTemplate, setServerTemplate] = useState(null);
  const [previewText, setPreviewText] = useState('');
  const [isDirty, setIsDirty] = useState(false);
  const [activeInputRef, setActiveInputRef] = useState(null);
  const [activeSectionKey, setActiveSectionKey] = useState(null);

  // Load existing template or default on mount
  useEffect(() => {
    fetchTemplate();
  }, [event.id]);

  const fetchTemplate = async () => {
    try {
      const res = await HTTP.get(`/events/${event.id}/invoice-template`);
      setTemplate(res.data);
      setServerTemplate(JSON.parse(JSON.stringify(res.data)));
      setIsDirty(false);
    } catch (err) {
      message.error('Gagal memuat template invoice');
    }
  };

  // Debounced preview generator
  const previewTimer = useRef(null);

  const fetchPreview = useCallback(async (currentData) => {
    if (!currentData) return;
    setPreviewLoading(true);
    try {
      const res = await HTTP.post(`/events/${event.id}/invoice-template/preview`, {
        pemisah: currentData.pemisah,
        bagian: currentData.bagian,
      });
      setPreviewText(res.data.teks);
    } catch (err) {
      // preview error silence
    } finally {
      setPreviewLoading(false);
    }
  }, [event.id]);

  useEffect(() => {
    if (!template) return;

    if (previewTimer.current) {
      clearTimeout(previewTimer.current);
    }

    previewTimer.current = setTimeout(() => {
      fetchPreview(template);
    }, 500);

    return () => {
      if (previewTimer.current) clearTimeout(previewTimer.current);
    };
  }, [template, fetchPreview]);

  // Check dirtiness
  useEffect(() => {
    if (!template || !serverTemplate) return;
    const isDifferent = JSON.stringify({ pemisah: template.pemisah, bagian: template.bagian }) !==
                        JSON.stringify({ pemisah: serverTemplate.pemisah, bagian: serverTemplate.bagian });
    setIsDirty(isDifferent);
  }, [template, serverTemplate]);

  // Check if template contains status placeholder
  const hasStatusPlaceholder = () => {
    if (!template || !template.bagian) return false;
    
    for (const key of SECTION_KEYS) {
      const section = template.bagian[key];
      if (section && section.aktif) {
        const teks = section.teks || '';
        if (teks.includes('{status}') || teks.includes('{status_ikon}')) {
          return true;
        }
      }
    }
    return false;
  };

  const handlePemisahChange = (e) => {
    setTemplate(prev => ({ ...prev, pemisah: e.target.value }));
  };

  const handleSectionActiveToggle = (key, e) => {
    e.stopPropagation();
    setTemplate(prev => ({
      ...prev,
      bagian: {
        ...prev.bagian,
        [key]: {
          ...prev.bagian[key],
          aktif: !prev.bagian[key].aktif,
        },
      },
    }));
  };

  const handleSectionTextChange = (key, field, value) => {
    setTemplate(prev => ({
      ...prev,
      bagian: {
        ...prev.bagian,
        [key]: {
          ...prev.bagian[key],
          [field]: value,
        },
      },
    }));
  };

  const handleSave = async () => {
    setLoading(true);
    try {
      const res = await HTTP.put(`/events/${event.id}/invoice-template`, {
        pemisah: template.pemisah,
        bagian: template.bagian,
      });
      setTemplate(res.data);
      setServerTemplate(JSON.parse(JSON.stringify(res.data)));
      setIsDirty(false);
      message.success('Template invoice berhasil disimpan');
    } catch (err) {
      if (err.response && err.response.status === 422) {
        const errors = err.response.data.errors;
        const msg = errors ? Object.values(errors).flat().join(', ') : 'Validasi gagal';
        message.error(msg);
      } else {
        message.error('Gagal menyimpan template invoice');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleReset = async () => {
    try {
      const res = await HTTP.get(`/events/${event.id}/invoice-template`);
      // Get default template values from backend default
      const defaultRes = await HTTP.get(`/events/${event.id}/invoice-template?default=1`);
      // Or fallback reset to server default
      if (res.data.is_default) {
        setTemplate(res.data);
      } else {
        // Fetch default config template if present, or fallback to state
        setTemplate({
          pemisah: '━━━━━━━━━━━━━━━━━━',
          bagian: {
            salam_pembuka: { aktif: true, teks: "Assalamu’alaikum Wr. Wb.\nShalom | Salam Sejahtera\n\n🌟 SUGENG RAWUH, SEDULUR NYAWIJI 🌟\n\nDengan hormat, kami sampaikan Invoice Pemesanan Tiket Event." },
            nama_event: { aktif: true, teks: "🎪 *{nama_event_besar}*\n📅 {tanggal_event_besar}\n⏰ START NAIK PUKUL 10.00 WIB" },
            pemesan: { aktif: true, teks: "👤 Nama     : {nama_pemesan}\n🔢 No. Urut : {nomor_urut}" },
            kelas_pesanan: { aktif: true, teks: "🎟️ KELAS PESANAN", format_baris: "{no}. {nama_sesi} — {harga} = {qty}" },
            total_pembayaran: { aktif: true, teks: "💰 TOTAL PEMBAYARAN\n👉 Rp {total_angka}" },
            ketentuan_pembayaran: { aktif: true, teks: "⚠️ KETENTUAN PEMBAYARAN\nPembayaran maksimal 1 × 24 jam setelah invoice diterima.\n\n💳 TRANSFER PEMBAYARAN\n🏦 BCA — 1234567890\n👤 A/N Nama Pemilik\n📲 Konfirmasi: 0812-0000-0000" },
            penutup: { aktif: true, teks: "🤝 SATU LANGKAH • SATU CERITA • SATU KELUARGA\n\nMaturnuwun & Salam Nyawiji. ❤️" },
            iklan: { aktif: false, teks: "" },
          },
        });
      }
      message.info('Editor telah direset ke template bawaan (belum disimpan)');
    } catch (err) {
      message.error('Gagal reset template');
    }
  };

  const handleInsertPlaceholder = (ph) => {
    if (!activeInputRef || !activeInputRef.element) {
      return;
    }

    const { element, sectionKey, fieldName } = activeInputRef;
    const start = element.selectionStart;
    const end = element.selectionEnd;
    const currentVal = template.bagian[sectionKey][fieldName] || '';

    const newVal = currentVal.substring(0, start) + ph + currentVal.substring(end);

    handleSectionTextChange(sectionKey, fieldName, newVal);

    setTimeout(() => {
      element.focus();
      element.setSelectionRange(start + ph.length, start + ph.length);
    }, 50);
  };

  if (!template) {
    return (
      <Modal open={true} footer={null} onCancel={onCancel}>
        <Spin style={{ display: 'block', margin: '40px auto' }} />
      </Modal>
    );
  }

  return (
    <Modal
      title={
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span>Pengaturan Invoice</span>
          {isDirty && <Tag color="warning">Ada perubahan belum disimpan</Tag>}
        </div>
      }
      open={true}
      onCancel={onCancel}
      footer={null}
      width={960}
      style={{ top: 20 }}
      bodyStyle={{ padding: '16px 24px' }}
    >
      <Form layout="vertical">
        <Form.Item label="Garis Pemisah" style={{ marginBottom: 16 }}>
          <Input
            value={template.pemisah}
            onChange={handlePemisahChange}
            maxLength={50}
            placeholder="━━━━━━━━━━━━━━━━━━"
          />
        </Form.Item>

        {!hasStatusPlaceholder() && (
          <Alert
            message="Template belum menampilkan status LUNAS/BELUM LUNAS. Sisipkan placeholder {status}."
            type="warning"
            showIcon
            style={{ marginBottom: 16 }}
          />
        )}

        <Row gutter={[16, 16]}>
          <Col xs={24} md={12}>
            <Collapse accordion onChange={(key) => setActiveSectionKey(key)}>
              {SECTION_KEYS.map((key) => {
                const section = template.bagian[key] || { aktif: false, teks: '' };
                return (
                  <Panel
                    key={key}
                    header={
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%', paddingRight: 8 }}>
                        <span>{SECTION_TITLES[key]}</span>
                        <Switch
                          checked={section.aktif}
                          onChange={(checked, e) => handleSectionActiveToggle(key, e)}
                        />
                      </div>
                    }
                  >
                    <Form.Item label="Teks Bagian" style={{ marginBottom: 8 }}>
                      <TextArea
                        rows={3}
                        value={section.teks}
                        onChange={(e) => handleSectionTextChange(key, 'teks', e.target.value)}
                        onFocus={(e) => setActiveInputRef({ element: e.target, sectionKey: key, fieldName: 'teks' })}
                        maxLength={2000}
                        showCount
                        autoSize={{ minRows: 3, maxRows: 12 }}
                      />
                    </Form.Item>

                    {key === 'kelas_pesanan' && (
                      <Form.Item
                        label="Format Baris"
                        extra="Dipakai untuk tiap sesi yang dipesan"
                        style={{ marginBottom: 8 }}
                      >
                        <Input
                          value={section.format_baris}
                          onChange={(e) => handleSectionTextChange(key, 'format_baris', e.target.value)}
                          onFocus={(e) => setActiveInputRef({ element: e.target, sectionKey: key, fieldName: 'format_baris' })}
                          maxLength={255}
                          placeholder="{no}. {nama_sesi} — {harga} = {qty}"
                        />
                      </Form.Item>
                    )}

                    <div style={{ marginTop: 12 }}>
                      <PlaceholderHelp
                        activeSectionKey={key}
                        onInsert={handleInsertPlaceholder}
                      />
                    </div>
                  </Panel>
                );
              })}
            </Collapse>
          </Col>

          <Col xs={24} md={12}>
            <div style={{ marginBottom: 8, fontWeight: 'bold' }}>Pratinjau Live:</div>
            <div style={{ position: 'relative' }}>
              {previewLoading && (
                <div style={{
                  position: 'absolute',
                  top: 0, left: 0, right: 0, bottom: 0,
                  background: 'rgba(255,255,255,0.7)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  zIndex: 10
                }}>
                  <Spin />
                </div>
              )}
              <pre style={{
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                fontSize: 13,
                fontFamily: 'monospace',
                background: '#fff',
                border: '1px solid #d9d9d9',
                borderRadius: 6,
                padding: 12,
                maxHeight: '70vh',
                overflowY: 'auto',
                minHeight: 300,
                margin: 0
              }}>
                {previewText || 'Memuat pratinjau...'}
              </pre>
            </div>
          </Col>
        </Row>

        <div style={{ textAlign: 'right', marginTop: 16 }}>
          <Popconfirm
            title="Reset editor ke template bawaan?"
            onConfirm={handleReset}
            okText="Ya"
            cancelText="Batal"
          >
            <Button style={{ marginRight: 8 }}>
              Reset ke Bawaan
            </Button>
          </Popconfirm>
          <Button style={{ marginRight: 8 }} onClick={onCancel}>
            Batal
          </Button>
          <Button type="primary" onClick={handleSave} loading={loading}>
            Simpan Template
          </Button>
        </div>
      </Form>
    </Modal>
  );
};

export default InvoiceTemplateModal;
