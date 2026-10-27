import React, { useState, useRef } from 'react';
import { Input, Switch, Form, Typography, Divider } from 'antd';
import PlaceholderHelp from './PlaceholderHelp';

const { TextArea } = Input;
const { Title } = Typography;

const TemplateSectionEditor = ({
  sectionKey,
  title,
  data,
  onChange,
}) => {
  const [focusedField, setFocusedField] = useState(null);

  const handleInsertPlaceholder = (placeholder) => {
    if (!focusedField) return;

    const inputRef = focusedField === 'title' ? titleRef : formatBarisRef;
    const inputEl = inputRef?.current?.input;

    if (inputEl) {
      const start = inputEl.selectionStart;
      const end = inputEl.selectionEnd;
      const text = data.teks || '';
      const newText = text.substring(0, start) + placeholder + text.substring(end);

      onChange({
        ...data,
        teks: newText,
      });

      setTimeout(() => {
        inputEl.focus();
        inputEl.setSelectionRange(start + placeholder.length, start + placeholder.length);
      }, 0);
    }
  };

  const handleFormatBarisInsert = (placeholder) => {
    const inputEl = formatBarisRef?.current?.input;
    if (inputEl) {
      const start = inputEl.selectionStart;
      const end = inputEl.selectionEnd;
      const text = data.format_baris || '';
      const newText = text.substring(0, start) + placeholder + text.substring(end);

      onChange({
        ...data,
        format_baris: newText,
      });

      setTimeout(() => {
        inputEl.focus();
        inputEl.setSelectionRange(start + placeholder.length, start + placeholder.length);
      }, 0);
    }
  };

  const titleRef = useRef();
  const formatBarisRef = useRef();

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
        <Title level={5}>{title}</Title>
        <Switch
          checked={data.aktif}
          onChange={(aktif) => onChange({ ...data, aktif })}
        />
      </div>

      <TextArea
        ref={titleRef}
        value={data.teks}
        onChange={(e) => onChange({ ...data, teks: e.target.value })}
        placeholder="Masukkan teks..."
        autoSize={{ minRows: 3, maxRows: 12 }}
        maxLength={2000}
        showCount
        style={{ marginBottom: 12 }}
        onFocus={() => setFocusedField('title')}
      />

      {sectionKey === 'kelas_pesanan' && (
        <div>
          <Form.Item
            label="Format baris"
            extra="Dipakai untuk tiap sesi yang dipesan"
            style={{ marginBottom: 8 }}
          >
            <Input
              ref={formatBarisRef}
              value={data.format_baris}
              onChange={(e) => onChange({ ...data, format_baris: e.target.value })}
              placeholder="{no}. {nama_sesi} — {harga} = {qty}"
              maxLength={255}
              showCount
              style={{ marginBottom: 8 }}
              onFocus={() => setFocusedField('formatBaris')}
            />
          </Form.Item>
          <PlaceholderHelp
            activeSectionKey={sectionKey}
            onInsert={handleFormatBarisInsert}
          />
        </div>
      )}

      {sectionKey !== 'kelas_pesanan' && (
        <PlaceholderHelp
          activeSectionKey={sectionKey}
          onInsert={handleInsertPlaceholder}
        />
      )}
    </div>
  );
};

export default TemplateSectionEditor;
